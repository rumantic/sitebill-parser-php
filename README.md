Для работы парсера yula.ru требуется
1. Запуск python-парсера в докере https://github.com/rumantic/sitebill-parser.git (он получает только заготовки)
2. Отправка данных в mongodb
3. Запуск https://github.com/rumantic/sitebill-parser-php (он распаковывает json-заготовки и отправляет их в mongodb)
4. Отдаем результаты парсинга этим https://github.com/rumantic/data-source-api.git

Схема в mongodb: youla.parsed
