<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use DateTime;
use Dakword\WBSeller\API\Endpoint\Adv;

class AdvSearchClusters
{
	private Adv $Adv;

	public function __construct(Adv $Adv)
	{
		$this->Adv = $Adv;
	}

	/**
	 * Статистика поисковых кластеров
	 *
	 * Максимум 10 запросов в минуту
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1stats/post
	 *
	 * @param DateTime $dateFrom Начало периода
	 * @param DateTime $dateTo Конец периода
	 * @param array<int, array<int, int>> $items Идентификаторы кампаний и товаров {advertId: int, nmId: int}
	 *
	 * @return object Статистика поисковых кластеров {"stats": [{
	 *     "advert_id": 1825035,
	 *     "nm_id": 983512347,
	 *     "stats": [
	 *       {
	 *         "atbs": 68,
	 *         "avg_pos": 3.6,
	 *         "clicks": 2090,
	 *         "cpc": 471,
	 *         "cpm": 813,
	 *         "ctr": 107.23,
	 *         "norm_query": "Фраза 1",
	 *         "orders": 19,
	 *         "views": 1949
	 *       }
	 *     ]
	 * }, ...]}
	 */
	public function normqueryStats(DateTime $dateFrom, DateTime $dateTo, array $items): object
	{
		return $this->Adv->postRequest('/adv/v0/normquery/stats', [
			'dateFrom' => $dateFrom->format('Y-m-d'),
			'dateTo'   => $dateTo->format('Y-m-d'),
			'items'    => $items,
		]);
	}

	/**
	 * Список ставок поисковых кластеров
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1get-bids/post
	 *
	 * @param array<int, array<int, int>> $items Идентификаторы кампаний и товаров {advertId: int, nmId: int}
	 * @return object Список ставок поисковых кластеров {
	 * "bids": [{
	 *   "advert_id": 1825035,
	 *   "bid": 700,
	 *   "nm_id": 983512347,
	 *   "norm_query": "Фраза 1"
	 * }, ...]
	 * }
	 */
	public function normqueryGetBids(array $items): object
	{
		return $this->Adv->postRequest('/adv/v0/normquery/get-bids', ['items' => $items]);
	}

	/**
	 * Установить ставки для поисковых кластеров
	 *
	 * Максимум 2 запроса в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1bids/post
	 *
	 * @param array $bids Идентификаторы кампаний и товаров [{	
	 *   "advert_id": 1825035,
	 *   "nm_id": 983512347,
	 *   "norm_query": "Фраза 1",
	 *   "bid": 1000
	 * }, ...]
	 * 
	 * @return bool
	 */
	public function normquerySetBids(array $bids): bool
	{
		$this->Adv->postRequest('/adv/v0/normquery/bids', ['bids' => $bids]);
		return $this->Adv->responseCode() === 200;
	}

	/**
	 * Удалить ставки для поисковых кластеров
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1bids/delete
	 *
	 * @param array $bids Идентификаторы кампаний и товаров [{	
	 *   "advert_id": 1825035,
	 *   "nm_id": 983512347,
	 *   "norm_query": "Фраза 1",
	 *   "bid": 1000
	 * }, ...]}
	 * 
	 * @return bool
	 */
	public function normqueryDeleteBids(array $bids): bool
	{
		$this->Adv->deleteRequest('/adv/v0/normquery/bids', ['bids' => $bids]);
		return $this->Adv->responseCode() === 200;
	}

	/**
	 * Список минус-фраз кампаний
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1get-minus/post
	 *
	 * @param array $items Идентификаторы кампаний и товаров [{	
	 *   "advert_id": 1825035,
	 *   "nm_id": 983512347
	 * }, ...]
	 * 
	 * @return object {
	 * "items": [{
	 *   "advert_id": 1825035,
	 *   "nm_id": 983512347,
	 *   "norm_queries": ["Фраза 1"]
	 * }, ...]
	 * }
	 */
	public function normqueryGetMinus(array $items): object
	{
		return $this->Adv->postRequest('/adv/v0/normquery/get-minus', ['items' => $items]) ?? [];
	}

	/**
	 * Установить минус-фразы для поисковых кластеров
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1set-minus/post
	 *
	 * @param int $advert_id Идентификатор кампании
	 * @param int $nm_id Идентификатор товара
	 * @param array<string> $norm_queries Минус-фразы
	 * 
	 * @return bool
	 */
	public function normquerySetMinus(int $advert_id, int $nm_id, array $norm_queries): bool
	{
		$this->Adv->postRequest('/adv/v0/normquery/set-minus', [
			'advert_id'    => $advert_id,
			'nm_id'        => $nm_id,
			'norm_queries' => $norm_queries,
		]);
		return $this->Adv->responseCode() === 200;
	}
}
