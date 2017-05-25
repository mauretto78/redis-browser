<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
namespace InMemoryList\Application;

use InMemoryList\Application\Exceptions\MalformedParametersException;
use InMemoryList\Application\Exceptions\NotSupportedDriverException;
use InMemoryList\Domain\Model\Contracts\ListRepository;
use InMemoryList\Infrastructure\Domain\Model\ListCollectionFactory;

class Client
{
    /**
     * @var string
     */
    private $driver;

    /**
     * @var ListRepository
     */
    private $repository;

    /**
     * Client constructor.
     *
     * @param string $driver
     * @param array  $parameters
     */
    public function __construct($driver = 'redis', array $parameters = [])
    {
        $this->_setDriver($driver);
        $this->_setRepository($driver, $parameters);
    }

    /**
     * @param $driver
     *
     * @throws NotSupportedDriverException
     */
    private function _setDriver($driver)
    {
        $allowedDrivers = [
            'apcu',
            'memcached',
            'redis'
        ];

        if (!in_array($driver, $allowedDrivers)) {
            throw new NotSupportedDriverException($driver.' is not a supported driver.');
        }

        $this->driver = $driver;
    }

    /**
     * @param $driver
     * @param array $parameters
     */
    private function _setRepository($driver, array $parameters = [])
    {
        $repository = 'InMemoryList\Infrastructure\Persistance\\'.ucfirst($driver).'Repository';
        $driver = 'InMemoryList\Infrastructure\Drivers\\'.ucfirst($driver).'Driver';
        $instance = (new $driver($parameters))->getInstance();

        $this->repository = new $repository($instance);
    }

    /**
     * @return ListRepository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @param array $elements
     * @param array $parameters
     * @return mixed|string
     * @throws MalformedParametersException
     */
    public function create(array $elements, array $parameters = [])
    {
        try {
            $this->_validateParameters($parameters);
            $factory = new ListCollectionFactory();
            $list = $factory->create(
                $elements,
                (isset($parameters['headers'])) ? $parameters['headers'] : [],
                (isset($parameters['uuid'])) ? $parameters['uuid'] : null,
                (isset($parameters['element-uuid'])) ? $parameters['element-uuid'] : null
            );

            return $this->repository->create(
                $list,
                (isset($parameters['ttl'])) ? $parameters['ttl'] : null,
                (isset($parameters['index'])) ? $parameters['index'] : null
            );
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * @param $parameters
     * @throws MalformedParametersException
     */
    private function _validateParameters($parameters)
    {
        $allowedParameters = [
            'chunk',
            'element-uuid',
            'headers',
            'index',
            'ttl',
            'uuid',
        ];

        foreach ($parameters as $key => $parameter) {
            if (!in_array($key, $allowedParameters)) {
                throw new MalformedParametersException();
            }
        }
    }

    /**
     * @param $uuid
     */
    public function delete($uuid)
    {
        $this->repository->delete($uuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function deleteElement($listUuid, $elementUuid)
    {
        return $this->repository->deleteElement($listUuid, $elementUuid);
    }

    /**
     * @param $listUuid
     *
     * @return mixed
     */
    public function findListByUuid($listUuid)
    {
        return $this->repository->findListByUuid($listUuid);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     *
     * @return mixed
     */
    public function findElement($listUuid, $elementUuid)
    {
        return $this->repository->findElement($listUuid, $elementUuid);
    }

    /**
     * @return mixed
     */
    public function flush()
    {
        $this->repository->flush();
    }

    /**
     * @return mixed
     */
    public function getCounter($listUuid)
    {
        return $this->repository->getCounter($listUuid);
    }

    /**
     * @return mixed
     */
    public function getHeaders($listUuid)
    {
        return $this->repository->getHeaders($listUuid);
    }

    /**
     * @return mixed
     */
    public function getIndex()
    {
        return $this->repository->getIndex();
    }

    /**
     * @return mixed
     */
    public function getStatistics()
    {
        return $this->repository->getStatistics();
    }

    /**
     * @param $string
     *
     * @return mixed
     */
    public function item($string)
    {
        return unserialize($string);
    }

    /**
     * @param $listUuid
     * @param $elementUuid
     * @param array $data
     *
     * @return mixed
     */
    public function updateElement($listUuid, $elementUuid, array $data = [])
    {
        return $this->repository->updateElement($listUuid, $elementUuid, $data);
    }

    /**
     * @param $listUuid
     * @param bool $ttl
     *
     * @return mixed
     */
    public function updateTtl($listUuid, $ttl = false)
    {
        return $this->repository->updateTtl($listUuid, $ttl);
    }
}
