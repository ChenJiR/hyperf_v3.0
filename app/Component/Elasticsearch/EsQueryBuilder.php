<?php


namespace App\Component\Elasticsearch;


use Hyperf\Contract\Arrayable;
use Hyperf\Context\ApplicationContext;
use Hyperf\Codec\Json;
use Hyperf\Contract\Jsonable;
use JsonSerializable;

class EsQueryBuilder implements Arrayable, Jsonable, JsonSerializable
{

    protected string $index;

    protected ?array $select_field;

    protected array $query;

    protected int $size = 10;

    protected int $from = 0;

    protected ?array $sort;

    protected ?string $collapse;

    protected ?array $highlight;

    protected ?int $min_score;

    public function __construct(string $index)
    {
        $this->index = $index;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @param array $select_field
     * @return EsQueryBuilder
     */
    public function setSelectField(array $select_field): EsQueryBuilder
    {
        !empty($select_field) && $this->select_field = $select_field;
        return $this;
    }

    /**
     * @param array $query
     * @return EsQueryBuilder
     */
    public function setQuery(array $query): EsQueryBuilder
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param array $sort
     * @return EsQueryBuilder
     */
    public function setSort(array $sort): EsQueryBuilder
    {
        $this->sort = $sort;
        return $this;
    }

    /**
     * @param string $collapse
     * @return EsQueryBuilder
     */
    public function setCollapse(string $collapse): EsQueryBuilder
    {
        $this->collapse = $collapse;
        return $this;
    }

    /**
     * @param int $size
     * @return EsQueryBuilder
     */
    public function setSize(int $size): EsQueryBuilder
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @param int $from
     * @return EsQueryBuilder
     */
    public function setFrom(int $from): EsQueryBuilder
    {
        $this->from = $from;
        return $this;
    }

    public function page(int $page, int $page_size): EsQueryBuilder
    {
        $this->size = $page_size;
        $page > 0 && $this->from = $page_size * ($page - 1);
        return $this;
    }

    public function generateEsQueryBody(): array
    {
        $body = ['query' => $this->query];
        !empty($this->select_field) && $body['_source'] = $this->select_field;
        !empty($this->sort) && $body['sort'] = $this->sort;
        !empty($this->from) && $body['from'] = $this->from;
        !empty($this->size) && $body['size'] = $this->size;
        !empty($this->collapse) && $body['collapse'] = ["field" => $this->collapse];
        !empty($this->highlight) && $body['highlight'] = $this->highlight;
        !empty($this->min_score) && $body['min_score'] = $this->min_score;
        return $body;
    }

    public function toArray(): array
    {
        return $this->generateEsQueryBody();
    }

    public function __toString(): string
    {
        return Json::encode($this->jsonSerialize());
    }

    public function jsonSerialize(): array
    {
        return $this->generateEsQueryBody();
    }

    public function search(): ?array
    {
        return ApplicationContext::getContainer()->get(ElasticsearchClient::class)->search($this);
    }

    /**
     * @param array $highlight
     * @return $this
     */
    public function setHighlight(array $highlight): EsQueryBuilder
    {
        $this->highlight = $highlight;
        return $this;
    }

    public function setMinScore(int $score): EsQueryBuilder
    {
        $this->min_score = $score;
        return $this;
    }


}