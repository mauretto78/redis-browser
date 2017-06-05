<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace InMemoryList\Infrastructure\Persistance;

use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListDoesNotExistsException;
use InMemoryList\Infrastructure\Persistance\Exceptions\ListElementDoesNotExistsException;
use Predis\Client;

class ApcuRepository extends AbstractRepository implements ListRepository
{
    /**
     * @var int
     */
    private $chunkSize;

    /**
     * ApcuRepository constructor.
     */
    public function __construct()
    {
        $this->chunkSize = self::CHUNKSIZE;
    }

    /**
     * @param ListCollection $list
     * @param null $ttl
     * @param null $chunkSize
     *
     * @return mixed
     *
     * @throws ListAlreadyExistsException
     */
    public function create(ListCollection $list, $ttl = null, $chunkSize = null)
    {
        if ($chunkSize and is_int($chunkSize)) {
            $this->chunkSize = $chunkSize;
        }

        $listUuid = (string) $list->getUuid();
        if ($this->findListByUuid($listUuid)) {
            throw new ListAlreadyExistsException('List '.$listUuid.' already exists in memory.');
        }

        // create arrayOfElements
        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($list->getItems() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = $element->getBody();
        }

        // persist in memory array in chunks
        $arrayChunks = array_chunk($arrayOfElements, $this->chunkSize, true);
        foreach ($arrayChunks as $chunkNumber => $item) {
            $arrayToPersist = [];
            foreach ($item as $key => $element) {
                $arrayToPersist[$key] = $element;
            }

            apcu_store(
                (string)$list->getUuid().self::SEPARATOR.'chunk-'.($chunkNumber+1),
                $arrayToPersist,
                $ttl
            );
        }

        // add list to index
        $this->_addOrUpdateListToIndex(
            (string)$listUuid,
            (int)count($list->getItems()),
            (int)count($arrayChunks),
            $ttl
        );

        // set headers
        if ($list->getHeaders()) {
            apcu_store(
                (string)$list->getUuid().self::SEPARATOR.self::HEADERS,
                $list->getHeaders(),
                $ttl
            );
        }

        return $this->findListByUuid($list->getUuid());
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param null $ttl
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid, $ttl = null)
    {
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        for ($i=1; $i<=$numberOfChunks; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {

                // delete elements from chunk
                unset($chunk[(string) $elementUuid]);
                apcu_delete($chunkNumber);
                apcu_store($chunkNumber, $chunk);

                // update list index
                $prevIndex = unserialize($this->getIndex($listUuid));
                $this->_addOrUpdateListToIndex(
                    $listUuid,
                    ($prevIndex['size'] - 1),
                    $numberOfChunks,
                    $prevIndex['ttl']
                );

                // delete headers if counter = 0
                $counter = $this->getCounter($listUuid);
                $headersKey = $listUuid . self::SEPARATOR . self::HEADERS;

                if ($counter === 0) {
                    apcu_delete($headersKey);
                }

                break;
            }
        }
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        $collection = [];
        $numberOfChunks = $this->getNumberOfChunks($listUuid);

        for ($i=1; $i<=$numberOfChunks; $i++) {
            if (empty($collection)) {
                $collection = apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-1');
            } else {
                $collection = array_merge($collection, apcu_fetch($listUuid.self::SEPARATOR.self::CHUNK.'-'.$i));
            }
        }

        return $collection;
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        apcu_clear_cache();
    }

    /**
     * @param $listUuid
     *
     * @return array
     */
    public function getHeaders($listUuid)
    {
        return apcu_fetch($listUuid.self::SEPARATOR.'headers');
    }

    /**
     * @param null $listUuid
     * @return mixed
     */
    public function getIndex($listUuid = null)
    {
        $indexKey = ListRepository::INDEX;

        if ($listUuid) {
            return apcu_fetch($indexKey)[(string)$listUuid];
        }

        return apcu_fetch($indexKey);
    }

    /**
     * @param $listUuid
     * @param $size
     * @param $numberOfChunks
     * @param null $ttl
     */
    private function _addOrUpdateListToIndex($listUuid, $size, $numberOfChunks, $ttl = null)
    {
        $indexKey = ListRepository::INDEX;
        $indexArray = serialize([
            'uuid' => $listUuid,
            'created_on' => new \DateTimeImmutable(),
            'size' => $size,
            'chunks' => $numberOfChunks,
            'ttl' => $ttl
        ]);

        if ($this->_existsListInIndex($listUuid)) {
            $index = apcu_fetch((string)$indexKey);
            $index[$listUuid] = $indexArray;

            apcu_delete($indexKey);
            apcu_store((string)$indexKey, $index);
        } else {
            apcu_store($indexKey, [(string)$listUuid => $indexArray]);
        }

        if ($size === 0) {
            $this->_removeListFromIndex($listUuid);
        }
    }

    /**
     * @param $listUuid
     */
    private function _removeListFromIndex($listUuid)
    {
        $indexKey = ListRepository::INDEX;
        $index = $this->getIndex();
        unset($index[(string) $listUuid]);

        apcu_delete($indexKey);
        apcu_store($indexKey, $index);
    }

    /**
     * @return array
     */
    public function getStatistics()
    {
        return (array) apcu_cache_info();
    }

    /**
     * @param $listUuid
     * @param ListElement $listElement
     * @return mixed
     */
    public function pushElement($listUuid, ListElement $listElement)
    {
        // TODO: Implement pushElement() method.
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     * @param null $ttl
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [], $ttl = null)
    {
        $number = ceil($this->getCounter($listUuid) / self::CHUNKSIZE);

        for ($i=1; $i<=$number; $i++) {
            $chunkNumber = $listUuid . self::SEPARATOR . self::CHUNK . '-' . $i;
            $chunk = apcu_fetch($chunkNumber);

            if (array_key_exists($elementUuid, $chunk)) {
                $element = $this->findElement($listUuid, $elementUuid);
                $objMerged = (object) array_merge((array) $element, (array) $data);
                $arrayOfElements = apcu_fetch($listUuid);
                $updatedElement = new ListElement(
                    new ListElementUuid($elementUuid),
                    $objMerged
                );
                $body = $updatedElement->getBody();
                $arrayOfElements[(string) $elementUuid] = $body;

                apcu_delete($chunkNumber);
                apcu_store(
                    (string)$chunkNumber,
                    $arrayOfElements,
                    $ttl
                );

                break;
            }
        }
    }

    /**
     * @param $listUuid
     * @param null $ttl
     *
     * @return mixed
     *
     * @throws ListDoesNotExistsException
     */
    public function updateTtl($listUuid, $ttl)
    {
        $list = $this->findListByUuid($listUuid);

        if (!$list) {
            throw new ListDoesNotExistsException('List '.$listUuid.' does not exists in memory.');
        }

        $this->_addOrUpdateListToIndex(
            $listUuid,
            $this->getCounter($listUuid),
            $this->getNumberOfChunks($listUuid),
            $ttl
        );
        apcu_delete($listUuid);
        apcu_store($listUuid, $list, $ttl);
    }
}
