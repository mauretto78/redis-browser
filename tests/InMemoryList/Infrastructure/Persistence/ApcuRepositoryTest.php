<?php
/**
 * This file is part of the InMemoryList package.
 *
 * (c) Mauro Cassani<https://github.com/mauretto78>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */
use InMemoryList\Domain\Model\ListElement;
use InMemoryList\Domain\Model\ListCollection;
use InMemoryList\Domain\Model\ListElementUuid;
use InMemoryList\Domain\Model\ListCollectionUuid;
use InMemoryList\Infrastructure\Persistance\ApcuRepository;
use PHPUnit\Framework\TestCase;

class ApcuRepositoryTest extends TestCase
{
    /**
     * @var ApcuRepository
     */
    private $repo;

    public function setUp()
    {
        $this->repo = new ApcuRepository();
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_the_list_from_apcu()
    {
        $fakeElement1 = new ListElement($fakeUUid1 = new ListElementUuid(), [
            'id' => 123,
            'title' => 'Lorem Ipsum',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 4,
        ]);
        $fakeElement2 = new ListElement($fakeUUid2 = new ListElementUuid(), [
            'id' => 124,
            'title' => 'Neque porro quisquam',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 5,
        ]);
        $fakeElement3 = new ListElement($fakeUUid3 = new ListElementUuid(), [
            'id' => 125,
            'title' => 'Ipso facto',
            'category-id' => 28,
            'category' => 'last minute',
            'rate' => 1,
        ]);
        $fakeElement4 = new ListElement($fakeUUid4 = new ListElementUuid(), [
            'id' => 126,
            'title' => 'Ipse dixit',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 3,
        ]);
        $fakeElement5 = new ListElement($fakeUUid5 = new ListElementUuid(), [
            'id' => 127,
            'title' => 'Dolor facius',
            'category-id' => 27,
            'category' => 'holiday',
            'rate' => 5,
        ]);

        $listUuid = new ListCollectionUuid();
        $collection = new ListCollection($listUuid);
        $collection->addItem($fakeElement1);
        $collection->addItem($fakeElement2);
        $collection->addItem($fakeElement3);
        $collection->addItem($fakeElement4);
        $collection->addItem($fakeElement5);

        $this->repo->create($collection);
        $this->repo->deleteElement($collection->getUuid()->getUuid(), $fakeElement5->getUuid()->getUuid());

        $list = $this->repo->findListByUuid($collection->getUuid());
        $element1 = unserialize($this->repo->findElement($collection->getUuid(), $fakeElement1->getUuid()->getUuid()));

        $this->assertCount(4, $list);
        $this->assertArrayHasKey('id', $element1);
        $this->assertArrayHasKey('title', $element1);
        $this->assertArrayHasKey('category-id', $element1);
        $this->assertArrayHasKey('category', $element1);
        $this->assertArrayHasKey('rate', $element1);

        $this->repo->delete($listUuid->getUuid());
    }

    /**
     * @test
     */
    public function it_should_create_query_and_delete_a_parsed_json_list_from_apcu()
    {
        $headers = [
            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
        ];

        $parsedArrayFromJson = json_decode(file_get_contents(__DIR__.'/../../../../examples/files/users.json'));

        $listUuid = new ListCollectionUuid();
        $collection = new ListCollection($listUuid);
        foreach ($parsedArrayFromJson as $element) {
            $collection->addItem(new ListElement($fakeUuid1 = new ListElementUuid(), $element));
        }
        $collection->setHeaders($headers);

        $this->repo->create($collection);

        $list = $this->repo->findListByUuid($collection->getUuid());
        $element = $this->repo->findElement($collection->getUuid(), $fakeUuid1->getUuid());

        $this->assertCount(10, $list);
        $this->assertInstanceOf(stdClass::class, unserialize($element));
        $this->assertEquals($this->repo->getHeaders($collection->getUuid()), $headers);
        $this->assertCount(10, $this->repo->getStatistics());
        $this->assertArrayHasKey($fakeUuid1->getUuid(), $this->repo->getStatistics());

        $statisticsElement1 = $this->repo->getStatistics()[$fakeUuid1->getUuid()];
        $created_on = unserialize($statisticsElement1)['created_on'];
        $this->assertInstanceOf(DateTimeImmutable::class, $created_on);

        $this->repo->delete($listUuid->getUuid());
    }
}