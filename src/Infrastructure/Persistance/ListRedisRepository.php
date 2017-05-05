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
use InMemoryList\Infrastructure\Persistance\Exception\CollectionAlreadyExistsException;
use InMemoryList\Infrastructure\Persistance\Exception\NotExistListElementException;
use Predis\Client;

class ListRedisRepository implements ListRepository
{
    /**
     * @var Client
     */
    private $client;

    /**
     * IMListRedisRepository constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * @param ListCollection $collection
     *
     * @return mixed
     *
     * @throws CollectionAlreadyExistsException
     */
    public function create(ListCollection $collection)
    {
        if ($this->findByUuid($collection->getUuid())) {
            throw new CollectionAlreadyExistsException('Collection '.$collection->getUuid().' already exists in memory.');
        }

        /** @var ListElement $element */
        foreach ($collection->getAll() as $element) {
            $this->client->hset(
                $collection->getUuid(),
                $element->getUuid(),
                serialize($element)
            );
        }

        return $this->findByUuid($collection->getUuid());
    }

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function delete($collectionUUId)
    {
        $collection = $this->findByUuid($collectionUUId);

        foreach ($collection as $element) {
            /** @var ListElement $element */
            $element = unserialize($element);
            $this->deleteElement($collectionUUId, $element->getUuid());
        }
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     */
    public function deleteElement($collectionUUId, $elementUUId)
    {
        $this->client->hdel($collectionUUId, $elementUUId);
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return bool
     */
    public function existsElement($collectionUUId, $elementUUId)
    {
        return @isset($this->findByUuid($collectionUUId)[$elementUUId]);
    }

    /**
     * @param $collectionUUId
     *
     * @return mixed
     */
    public function findByUuid($collectionUUId)
    {
        return $this->client->hgetall($collectionUUId);
    }

    /**
     * @param $collectionUUId
     * @param $elementUUId
     *
     * @return mixed
     *
     * @throws NotExistListElementException
     */
    public function findElement($collectionUUId, $elementUUId)
    {
        if (!$this->existsElement($collectionUUId, $elementUUId)) {
            throw new NotExistListElementException('Cannot retrieve the element '.$elementUUId.' from the collection in memory.');
        }

        return unserialize($this->findByUuid($collectionUUId)[$elementUUId]);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->client->flushall();
    }
}
