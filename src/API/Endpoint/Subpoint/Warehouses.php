<?php

declare(strict_types=1);

namespace Dakword\WBSeller\API\Endpoint\Subpoint;

use Dakword\WBSeller\API\Response\ApiResponse;

use Dakword\WBSeller\API\Endpoint\Marketplace;

class Warehouses
{
    private Marketplace $Marketplace;

    public function __construct(Marketplace $Marketplace)
    {
        $this->Marketplace = $Marketplace;
    }

    /**
     * Cписок складов продавца
     * 
     * @return ApiResponse
     */
    public function list(): ApiResponse {
        return $this->Marketplace->getRequest('/api/v3/warehouses');
    }

    /**
     * Cписок складов WB
     * 
     * Возвращает список всех складов WB для привязки к складам продавца
     * 
     * @return ApiResponse
     */
    public function offices(): ApiResponse {
        return $this->Marketplace->getRequest('/api/v3/offices');
    }

    /**
     * Создать склад продавца
     * 
     * Нельзя привязывать склад WB, который уже используется.
     * 
     * @param string $name     Имя склада (до 200 символов)
     * @param int    $officeId ID склада WB к которому привязать
     * 
     * @return ApiResponse
     */
    public function create(string $name, int $officeId): ApiResponse {
        return $this->Marketplace->postRequest('/api/v3/warehouses', [
            'name' => mb_substr($name, 0, 200),
            'officeId' => $officeId,
        ]);
    }

    /**
     * Обновить склад продавца
     * 
     * Изменение выбранного склада WB допустимо раз в сутки.
     * Нельзя привязывать склад WB, который уже используется. 
     * 
     * @param int    $id       ID склада продавца
     * @param string $name     Имя склада (до 200 символов)
     * @param int    $officeId ID склада WB к которому привязать
     * 
     * @return ApiResponse
     */
    public function update(int $id, string $name, int $officeId): ApiResponse {
        return $this->Marketplace->putRequest('/api/v3/warehouses/' . $id, [
            'name' => mb_substr($name, 0, 200),
            'officeId' => $officeId,
        ]);
    }

    /**
     * Удалить склад продавца
     * 
     * @param int    $id       ID склада продавца
     * 
     * @return ApiResponse
     */
    public function delete(int $id): ApiResponse {
        return $this->Marketplace->deleteRequest('/api/v3/warehouses/' . $id,);
    }    
    
}
