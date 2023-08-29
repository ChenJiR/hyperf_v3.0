<?php

namespace App\Component\Elasticsearch;

use App\Logger\Log;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Hyperf\Codec\Json;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Elasticsearch\ClientBuilderFactory;
use Hyperf\Context\ApplicationContext;
use Hyperf\Guzzle\RingPHP\CoroutineHandler;
use Hyperf\Guzzle\RingPHP\PoolHandler;
use function Hyperf\Support\env;
use function Hyperf\Support\make;


class ElasticsearchClient
{

    protected Client $es_client;

    public function __construct()
    {
//        $client_builder = ApplicationContext::getContainer()->get(ClientBuilderFactory::class);
//        $builder = $client_builder->create();

        $builder = ClientBuilder::create();
        if (Coroutine::inCoroutine()) {
            $handler = make(PoolHandler::class, [
                'option' => [
                    'max_connections' => 45,
                ],
            ]);
            $builder->setHandler($handler);
        }

        $host = [];
        foreach (explode(',', env("ELASTIC_CLUSTER", '')) as $item) {
            $host[] = env("ELASTIC_USERNAME", '') . ':' . env("ELASTIC_PASSWORD", '') . '@' . $item . ':' . env("");
        }

        $this->es_client = $builder->setHosts($host)->build();
    }

    public function getEsClient(): Client
    {
        return $this->es_client;
    }

    public function search(EsQueryBuilder $query): ?array
    {
        $params = ['index' => $query->getIndex(), 'body' => $query->generateEsQueryBody()];
        Log::info('esQueryBody', ['query' => Json::encode($params)], 'esQueryBody');
        try {
            return $this->es_client->search($params)['hits'];
        } catch (Missing404Exception) {
            return null;
        }
    }

    /**
     * @param $id
     * @param string $index
     * @return callable|array|null
     */
    public function find($id, string $index): callable|array|null
    {
        try {
            return $this->es_client->get(['index' => $index, 'id' => $id]);
        } catch (Missing404Exception) {
            return null;
        }
    }

    /**
     * @param array $ids
     * @param string $index
     * @param array|null $source
     * @return array|null
     */
    public function findMultiple(array $ids, string $index, ?array $source = null): array|null
    {
        if (empty($ids)) return [];
        $params = [
            'index' => $index,
            'body' => ['ids' => $ids]
        ];
        !empty($source) && $params['_source'] = $source;
        try {
            return $this->es_client->mget($params)['docs'];
        } catch (Missing404Exception) {
            return null;
        }
    }

    /**
     * @param $id
     * @param string $index
     * @return bool
     */
    public function exists($id, string $index): bool
    {
        return $this->es_client->exists(['index' => $index, 'id' => $id]);
    }

    /**
     * @param $id
     * @param string $index
     * @return bool
     */
    public function delete($id, string $index): bool
    {
        $res = $this->es_client->delete(['index' => $index, 'id' => $id]);
        return isset($res['_shards']['successful']);
    }

    /**
     * @param $id
     * @param $doc
     * @param string $index
     * @return callable|array
     */
    public function edit($id, $doc, string $index): callable|array
    {
        $params = [
            'index' => $index,
            'id' => $id,
            'body' => $doc
        ];

        return $this->es_client->index($params);
    }

}