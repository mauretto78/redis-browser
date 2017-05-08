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

use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Infrastructure\Persistance\Exception\CollectionAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException;

class ListMemcachedRepository implements ListRepository
{
    /**
     * @var \Memcached
     */
    private $memcached;

    /**
     * ListMemcachedRepository constructor.
     *
     * @param \Memcached $memcached
     */
    public function __construct(\Memcached $memcached)
    {
        $this->memcached = $memcached;
    }

    /**
     * @param ListCollection $collection
     *
     * @return mixed
     *
     * @throws CollectionAlreadyExistsException
     */
    public function create(ListCollection $collection, $ttl = null)
    {
        if ($this->findByUuid($collection->getUuid())) {
            throw new CollectionAlreadyExistsException('Collection '.$collection->getUuid().' already exists in memory.');
        }

        $arrayOfElements = [];

        /** @var ListElement $element */
        foreach ($collection->getAll() as $element) {
            $arrayOfElements[(string) $element->getUuid()] = serialize($element);
        }

        $this->memcached->set(
            $collection->getUuid(),
            $arrayOfElements,
            $ttl
        );

        return $this->findByUuid($collection->getUuid());
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function delete($collectionUuid)
    {
        $this->memcached->delete($collectionUuid);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @throws NotExistListElementException
     */
    public function deleteElement($collectionUuid, $elementUuid)
    {
        $arrayToReplace = $this->findByUuid($collectionUuid);
        unset($arrayToReplace[(string) $elementUuid]);

        $this->memcached->replace($collectionUuid, $arrayToReplace);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return bool
     */
    public function existsElement($collectionUuid, $elementUuid)
    {
        return @isset($this->findByUuid($collectionUuid)[$elementUuid]);
    }

    /**
     * @param $collectionUuid
     *
     * @return mixed
     */
    public function findByUuid($collectionUuid)
    {
        return $this->memcached->get($collectionUuid);
    }

    /**
     * @param $collectionUuid
     * @param $elementUuid
     *
     * @return mixed
     *
     * @throws NotExistListElementException
     */
    public function findElement($collectionUuid, $elementUuid)
    {
        if (!$this->existsElement($collectionUuid, $elementUuid)) {
            throw new NotExistListElementException('Cannot retrieve the element '.$elementUuid.' from the collection in memory.');
        }

        return unserialize($this->memcached->get($collectionUuid)[(string) $elementUuid]);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->memcached->flush();
    }
}
