<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\Endpoint\Adv;
use DateTime;
use InvalidArgumentException;

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
	 * @return ApiResponse
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
	public function normqueryStats(DateTime $dateFrom, DateTime $dateTo, array $items): ApiResponse {
		$body = [
			'from'  => $dateFrom->format('Y-m-d'),
			'to'    => $dateTo->format('Y-m-d'),
			'items' => $items,
		];
		return $this->Adv->postRequest('/adv/v0/normquery/stats', $body);
	}

	/**
	 * Статистика поисковых кластеров с группировкой по дням
	 *
	 * Максимум 10 запросов в минуту
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1stats/post
	 *
	 * @param DateTime $dateFrom Начало периода
	 * @param DateTime $dateTo Конец периода
	 * @param array<int, array<int, int>> $items Идентификаторы кампаний и товаров, не более 100 {advertId: int, nmId: int}
	 *
	 * @return ApiResponse
   *  "items": [
   *    {
   *      "advertId": 123456789,
   *      "dailyStats": [
   *        {
   *          "date": "2026-01-27",
   *          "stat": {
   *            "atbs": 39,
   *            "avgPos": 3.3,
   *            "clicks": 75,
   *            "cpc": 1.44,
   *            "cpm": 562.5,
   *            "ctr": 39.06,
   *            "normQuery": "Поисковый кластер 0",
   *            "orders": 9,
   *            "shks": 5,
   *            "spend": 108,
   *            "views": 192
   *          }
   *        },
   *        {
   *          "date": "2026-01-27",
   *          "stat": {
   *            "atbs": 71,
   *            "avgPos": 7.9,
   *            "clicks": 56,
   *            "cpc": 4.38,
   *            "cpm": 1290.95,
   *            "ctr": 29.47,
   *            "normQuery": "румяна для лица vivienne sabo",
   *            "orders": 2,
   *            "shks": 44,
   *            "spend": 245.28,
   *            "views": 190
   *          }
   *        },
   *        {
   *          "date": "2026-01-27",
   *          "stat": {
   *            "atbs": 39,
   *            "avgPos": 3.3,
   *            "clicks": 75,
   *            "cpc": 1.44,
   *            "cpm": 562.5,
   *            "ctr": 39.06,
   *            "normQuery": "Поисковый кластер 2",
   *            "orders": 9,
   *            "shks": 345345,
   *            "spend": 108,
   *            "views": 192
   *          }
   *        }
   *      ],
   *      "nmId": 987654321
   *    }
   *  ]
   *}
	 */
	public function normqueryStatsV1(
		DateTime $dateFrom, 
		DateTime $dateTo, 
		array $items
	): ApiResponse {
		if (empty($items)) throw new InvalidArgumentException("Массив items не должен быть пустым");
		if (count($items) > 100) throw new InvalidArgumentException("Превышено максимальное количество элементов в items: 100");
		$interval = $dateFrom->diff($dateTo);
		if ($interval->days > 30) throw new InvalidArgumentException("Интервал между датами не должен превышать 30 дней включительно");
		$body = [
			'from'  => $dateFrom->format('Y-m-d'),
			'to'    => $dateTo->format('Y-m-d'),
			'items' => $items,
		];
		return $this->Adv->postRequest('/adv/v1/normquery/stats', $body);
	}

	/**
	 * Список ставок поисковых кластеров
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/openapi/promotion/#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1get-bids/post
	 *
	 * @param array<int, array<int, int>> $items Идентификаторы кампаний и товаров {advertId: int, nmId: int}
	 * @return ApiResponse
	 * "bids": [{
	 *   "advert_id": 1825035,
	 *   "bid": 700,
	 *   "nm_id": 983512347,
	 *   "norm_query": "Фраза 1"
	 * }, ...]
	 * }
	 */
	public function normqueryGetBids(array $items): ApiResponse {
		$body = [
			'items' => $items,
		];
		return $this->Adv->postRequest('/adv/v0/normquery/get-bids', $body);
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
	 * @return ApiResponse
	 */
	public function normquerySetBids(array $bids): ApiResponse {
		$body = [
			'bids' => $bids,
		];
		return $this->Adv->postRequest('/adv/v0/normquery/bids', $body);
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
	 * @return ApiResponse
	 */
	public function normqueryDeleteBids(array $bids): ApiResponse {
		$body = [
			'bids' => $bids,
		];
		return $this->Adv->deleteRequest('/adv/v0/normquery/bids', $body);
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
	 * @return ApiResponse
	 * "items": [{
	 *   "advert_id": 1825035,
	 *   "nm_id": 983512347,
	 *   "norm_queries": ["Фраза 1"]
	 * }, ...]
	 * }
	 */
	public function normqueryGetMinus(array $items): ApiResponse {
		$body = [
			'items' => $items,
		];
		return $this->Adv->postRequest('/adv/v0/normquery/get-minus', $body);
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
	 * @return ApiResponse
	 */
	public function normquerySetMinus(int $advert_id, int $nm_id, array $norm_queries): ApiResponse {
		return $this->Adv->postRequest('/adv/v0/normquery/set-minus', [
			'advert_id'    => $advert_id,
			'nm_id'        => $nm_id,
			'norm_queries' => $norm_queries,
		]);
	}

	/**
	 * Списки активных и неактивных поисковых кластеров
	 *
	 * Максимум 5 запросов в секунду
	 * @link https://dev.wildberries.ru/docs/openapi/promotion#tag/Poiskovye-klastery/paths/~1adv~1v0~1normquery~1set-minus/post
	 *
	 * @param array $items Идентификаторы кампаний и товаров [{	
	 *   "advertId": 1825035,
	 *   "nmId": 983512347
	 * }, ...]
	 * 
	 * @return ApiResponse
	 * "items": [{
	 *   "advertId": 1825035,
	 *   "nmId": 983512347,
	 *   "normQueries": ["active": ["Фраза 1"], "excluded": ["Фраза 2"]]
	 * }, ...]
	 * }
	 */
	public function normqueryList(array $items): ApiResponse {
		$body = [
			'items' => $items,
		];
		return $this->Adv->postRequest('/adv/v0/normquery/list', $body);
	}
}
