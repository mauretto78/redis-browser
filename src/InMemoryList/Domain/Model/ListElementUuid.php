<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace InMemoryList\Domain\Model;

use InMemoryList\Domain\Model\Contracts\ListRepositoryInterface;
use InMemoryList\Domain\Model\Exceptions\ListElementNotAllowedUuidException;
use Ramsey\Uuid\Uuid;

class ListElementUuid
{
    /**
     * @var string
     */
    private $uuid;

    /**
     * ListElementUUId constructor.
     *
     * @param null $uuid
     */
    public function __construct($uuid = null)
    {
        $this->setUUid($uuid);
    }

    /**
     * @param null $uuid
     *
     * @throws ListElementNotAllowedUuidException
     */
    public function setUUid($uuid = null)
    {
        $notAllowedNames = [
            ListRepositoryInterface::CHUNK,
            ListRepositoryInterface::HEADERS,
            ListRepositoryInterface::INDEX,
            ListRepositoryInterface::SEPARATOR,
            ListRepositoryInterface::STATISTICS,
        ];

        foreach ($notAllowedNames as $notAllowedName) {
            if (strpos($uuid, $notAllowedName) !== false) {
                throw new ListElementNotAllowedUuidException('You can\'t assign "'.$uuid.'" as element uuid.');
            }
        }

        $this->uuid = str_replace(' ', '-', $uuid) ?: Uuid::uuid4()->toString();
    }

    /**
     * @return mixed
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->getUuid();
    }
}
