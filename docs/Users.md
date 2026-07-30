## [WBSeller API](/docs/API.md) / Users()

```php
$wbSellerAPI = new \Dakword\WBSeller\API($options);
$Users = $wbSellerAPI->Users();
```

Wildberries API / **Управление пользователями продавца**

| :speech_balloon: | :cloud: | [Users()](/src/API/Endpoint/Users.php) |
| ---------------- | ------- | ------------------------------------- |
| Пригласить пользователя | /api/v1/invite | Users()->**invite()** |
| Получить пользователей | /api/v1/users | Users()->**list()** |
| Изменить права пользователей | /api/v1/users/access | Users()->**updateAccess()** |
| Закрыть доступ пользователю | /api/v1/user | Users()->**delete()** |
