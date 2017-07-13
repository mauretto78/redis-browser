<?php

use InMemoryList\Application\Client;
use InMemoryList\Command\IndexCommand;
use InMemoryList\Tests\BaseTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class IndexCommandTest extends BaseTestCase
{
    /**
     * @var Application
     */
    private $app;

    private $array;

    public function setUp()
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->app = new Application();
        $this->app->add(new IndexCommand());

        $this->array = json_encode([
            [
                'userId' => 1,
                'id' => 1,
                'title' => 'sunt aut facere repellat provident occaecati excepturi optio reprehenderit',
                'body' => "quia et suscipit\nsuscipit recusandae consequuntur expedita et cum\nreprehenderit molestiae ut ut quas totam\nnostrum rerum est autem sunt rem eveniet architecto",
            ],
            [
                'userId' => 1,
                'id' => 2,
                'title' => 'qui est esse',
                'body' => "est rerum tempore vitae\nsequi sint nihil reprehenderit dolor beatae ea dolores neque\nfugiat blanditiis voluptate porro vel nihil molestiae ut reiciendis\nqui aperiam non debitis possimus qui neque nisi nulla",
            ],
        ]);
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_apcu_cache()
    {
        $this->app->add(new IndexCommand('apcu'));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[apcu] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_apcu_cache()
    {
        $client = new Client('apcu');
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
        ]);

        $this->app->add(new IndexCommand('apcu'));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'from' => 'yesterday',
            'to' => 'now',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('1', $output);
        $this->assertContains('2', $output);

        $client->getRepository()->flush();
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_memcached_cache()
    {
        $this->app->add(new IndexCommand('memcached', $this->memcached_parameters));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[memcached] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_memcached_cache()
    {
        $client = new Client('memcached', $this->memcached_parameters);
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
        ]);

        $this->app->add(new IndexCommand('memcached', $this->memcached_parameters));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('simple-list', $output);
        $this->assertContains('0', $output);
        $this->assertContains('2', $output);

        $client->getRepository()->flush();
    }

    /**
     * @test
     */
    public function it_displays_correctly_empty_redis_cache()
    {
        $this->app->add(new IndexCommand('redis', $this->redis_parameters));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('[redis] Empty Index.', $output);
    }

    /**
     * @test
     */
    public function it_displays_correctly_index_redis_cache()
    {
        $headers = [
            'expires' => 'Sat, 26 Jul 1997 05:00:00 GMT',
            'hash' => 'ec457d0a974c48d5685a7efa03d137dc8bbde7e3',
        ];
        $client = new Client('redis', $this->redis_parameters);
        $client->create(json_decode($this->array), [
            'uuid' => 'simple-list',
            'element-uuid' => 'id',
            'ttl' => 3600,
            'headers' => $headers,
        ]);

        $this->app->add(new IndexCommand('redis', $this->redis_parameters));
        $command = $this->app->find('iml:cache:index');

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'from' => 'yesterday',
            'to' => 'now',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertContains('simple-list', $output);
        $this->assertContains('3600', $output);
        $this->assertContains('2', $output);
        $this->assertContains('expires=Sat, 26 Jul 1997 05:00:00 GMT, hash=ec457d0a974c48d5685a7efa03d137dc8bbde7e3', $output);

        $client->getRepository()->flush();
    }
}
