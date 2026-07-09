# TP-Link Catalog Import (Bitrix)

CLI-импортёр каталога Wi‑Fi роутеров TP-Link с официальной страницы [tp-link.com/kz](https://www.tp-link.com/kz/home-networking/wifi-router/) в инфоблок Битрикс.

Репозиторий с докер-контейнерами для битрикса - https://github.com/bitrix-tools/env-docker?ysclid=mrdexxqzkm709088279

## Как развернуть на чистом Битриксе

1. Клонируйте репозиторий в корень сайта (document root).
2. Установите зависимости:
   ```bash
   cd local
   composer install
   ```
   > **Важно:** `composer install` подтягивает `bitrix/composer-bx.json` (Symfony Console для `bitrix.php`). Без этого команда `php bitrix.php ...` выдаст `Symfony Console is not installed`.
   > **Важно:** не перезаписывайте существующий `local/.settings.php` на сервере — в репозитории только `local/.settings_extra.php` с путём к Composer. Если сайт упал с 500 после копирования, восстановите свой `.settings.php` из бэкапа.
3. Установите модуль миграций в админке: **Настройки → Настройки продукта → Модули → sprint.migration**.
4. Установите модуль импорта: **yd.tplink.import** (тот же раздел модулей).
5. Выполните миграцию инфоблока:
   ```bash
   php bitrix/bitrix.php sprint:migration up
   ```
   или через админку sprint.migration.
6. Запустите импорт:
   ```bash
   php bitrix/bitrix.php yd.tplink.import:run
   ```
7. Проверьте лог: `/local/logs/tplink_import_YYYY-MM-DD_HH-MM-SS.json` и элементы инфоблока `tplink_catalog_stage`.

## Зависимости

| Компонент | Версия |
|---|---|
| 1C-Bitrix | 25.x (минимум 23.0, CLI `bitrix.php` — с 25.900) |
| PHP | 8.2+ |
| Модули ядра | `iblock`, `main` |
| Composer-пакеты | `andreyryabin/sprint.migration` ^5.0 |

PHP-расширения: `curl`, `json`, `mbstring`, `openssl`.

## Как запустить импорт одной командой

```bash
php bitrix/bitrix.php yd.tplink.import:run
```

Веб-точка входа **не предусмотрена** — только CLI.

## Hash Check

После первого успешного прогона сверьте хэш полного списка артикулов:

```
sha256(sorted_articles) = 63abea412f36a0a3b0005c488cf8988b3456a29538b816d985916c399c045499
```

Хэш считается как `SHA256` от отсортированного списка значений `article` (где `article = FULL_ARTICLE`), соединённых через `\n`.

## Ограничения текущей реализации

- Источник жёстко задан: категория Wi‑Fi Router, регион `kz`.
- Парсинг HTML/фрагментов листинга (`action=getfragment`) — при изменении вёрстки tp-link.com потребуется доработка.
- Характеристики (`WIFI_*`, `WAN_SPEED`, `LAN_PORTS`) берутся из таблицы спецификаций карточки; если блок пуст, поля остаются пустыми.
- При недоступной/пустой support-странице создаётся fallback-элемент с `NEEDS_REVIEW=Y`.
- Импорт не удаляет элементы — только `MISSING_AT_SOURCE=Y`.
- Нет rate-limit/backoff кроме таймаутов HTTP-клиента.

## Что бы сделали за следующие 4 часа

1. Кэш HTTP-ответов и параллельная загрузка карточек (с лимитом concurrency).
2. Юнит-тесты парсеров на сохранённых HTML-фикстурах.
3. Опции CLI: `--dry-run`, `--limit`, `--slug=archer-ax72`.
4. Агент/cron-обёртка и уведомление при `errors > 0`.
5. Админ-страница просмотра последнего лога (без запуска импорта через веб).

## Обоснование архитектурных решений

### Расположение кода — модуль `yd.tplink.import`

Выбран отдельный модуль в `/local/modules/yd.tplink.import/` вместо `/local/tools/`:

- консольная команда регистрируется штатно через `.settings.php`;
- сервисы изолированы и доступны через `ServiceLocator`;
- миграции структуры данных отделены от логики импорта (`sprint.migration`).

### Инфоблок vs HL-блок

Использован **инфоблок** типа `test_catalog`:

- ТЗ явно требует инфоблок с именованными свойствами каталога;
- не нужна отдельная пользовательская админка HL;
- тип `test_catalog` выбран вместо `catalog`, чтобы не смешивать тестовый импорт с боевым торговым каталогом и SKU/ценами.

### Как парсится источник

1. **Листинг** — AJAX-фрагменты `?action=getfragment&page=N` (тот же механизм, что у `product-list.js` на сайте TP-Link).
2. **Карточка** — HTML: имя модели, хлебные крошки, спецификации, HW-версии в шапке.
3. **Support** — `/kz/support/download/{slug}/` и подстраницы `/v1/`, `/v2/`…; регион и HW из имён файлов прошивок (`Archer AX55(EU)_V4_...`).
4. **Сценарий A** — несколько элементов на комбинации (модель, регион, HW).
5. Приоритетное правило — разворачивание `HW × region` (одна карточка источника порождает несколько элементов).

## Использование ИИ

Инструмент: **Cursor (Claude)**.

ИИ использовался на этапах:

- уточнение и фиксация ТЗ (идемпотентность, сценарии A/B, структура свойств);
- проектирование структуры модуля и миграции;
- разбор HTML/API страниц tp-link.com (фрагменты листинга, support-страницы).

Примеры промптов:

> «Собрать все модели роутеров с https://www.tp-link.com/kz/home-networking/wifi-router/, полный артикул через support/download, идемпотентность по FULL_ARTICLE»

> «Сделать CLI-модуль yd.tplink.import на D7, миграция sprint.migration, без веб-точки входа»

## Структура репозитория

```
local/
├── composer.json
├── modules/yd.tplink.import/     # модуль импорта
├── php_interface/migrations/     # миграция инфоблока
└── logs/                         # JSON-логи импорта
scripts/export_csv.php            # генерация CSV для проверки (без Битрикс)
tplink_import_first_run.csv
tplink_import_second_run.csv
```
