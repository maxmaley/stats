# 🔍 AIWU Analytics - Code Review Report

## Структура данных

**Таблицы:**
- `wp_lms_stats` - основная таблица статистики
- `wp_lms_stats_details` - детальная статистика

**mode значения:**
- `mode=0` - сохранение статистики (каждые 7 дней)
- `mode=1` - первая активация плагина
- `mode=2` - деактивация плагина

---

## 🚨 Критические проблемы

### 1. **НЕПРАВИЛЬНЫЙ Churn Rate** ⚠️⚠️⚠️
**Локация:** `class-database.php:108-127`

**Текущий код:**
```php
public function get_churn_rate($date_from, $date_to) {
    $total = $this->get_total_installations($date_from, $date_to); // Активации в периоде
    $deactivations = ...WHERE mode = 2...; // Деактивации в периоде
    $churn_rate = ($deactivations / $total) * 100;
}
```

**Проблема:**
- Считает: `деактивации в периоде / активации в периоде * 100`
- Это НЕ churn rate! Может быть > 100%
- Если в периоде 10 активаций и 20 деактиваций = 200% 🤯

**Правильная формула:**
```
Churn Rate = (Деактивации в периоде / Активные пользователи на начало периода) * 100
```

**Исправление:**
```php
public function get_churn_rate($date_from, $date_to) {
    // Пользователи активные на начало периода
    $active_at_start = $this->wpdb->get_var($this->wpdb->prepare(
        "SELECT COUNT(DISTINCT email) FROM {$this->stats_table}
         WHERE email IN (
             SELECT email FROM {$this->stats_table} WHERE mode = 1 AND created < %s
         )
         AND email NOT IN (
             SELECT email FROM {$this->stats_table} WHERE mode = 2 AND created < %s
         )",
        $date_from, $date_from
    ));

    // Деактивации в периоде
    $deactivations = $this->wpdb->get_var($this->wpdb->prepare(
        "SELECT COUNT(DISTINCT email) FROM {$this->stats_table}
         WHERE mode = 2 AND created BETWEEN %s AND %s",
        $date_from, $date_to
    ));

    $churn_rate = $active_at_start > 0 ? ($deactivations / $active_at_start) * 100 : 0;

    return array(
        'active_at_start' => $active_at_start,
        'deactivations' => $deactivations,
        'churn_rate' => round($churn_rate, 2)
    );
}
```

---

### 2. **НЕПРАВИЛЬНЫЙ Active Users**
**Локация:** `class-database.php:53-62`

**Текущий код:**
```php
public function get_active_users($date_from, $date_to) {
    $where = "WHERE mode = 0"; // mode=0 is stats ping
    // COUNT(DISTINCT email)
}
```

**Проблема:**
- Считает только пользователей, которые отправили статистику (mode=0) в периоде
- Если период 30 дней, а статистика раз в 7 дней, то пользователь попадет только в ~4 дня из 30
- Метрика "Active Users" должна показывать всех активных пользователей, не только тех, кто отправил ping

**Правильное определение:**
```
Active Users = Пользователи, которые:
  1. Активировали плагин (mode=1)
  2. ЕЩЕ НЕ деактивировали (нет mode=2 после mode=1)
  3. Отправили хотя бы 1 статистику в периоде (mode=0)
```

**Исправление:**
```php
public function get_active_users($date_from, $date_to) {
    $query = $this->wpdb->prepare(
        "SELECT COUNT(DISTINCT s.email) as total
         FROM {$this->stats_table} s
         WHERE s.mode = 0
         AND s.created BETWEEN %s AND %s
         AND EXISTS (
             SELECT 1 FROM {$this->stats_table} s2
             WHERE s2.email = s.email AND s2.mode = 1
         )
         AND NOT EXISTS (
             SELECT 1 FROM {$this->stats_table} s3
             WHERE s3.email = s.email
             AND s3.mode = 2
             AND s3.created > (
                 SELECT MAX(created) FROM {$this->stats_table} s4
                 WHERE s4.email = s.email AND s4.mode = 1
             )
         )",
        $date_from, $date_to
    );

    return (int) $this->wpdb->get_var($query);
}
```

---

### 3. **НЕПРАВИЛЬНЫЙ Conversion Rate**
**Локация:** `class-database.php:67-103`

**Текущий код:**
```php
$free_users_query = "...WHERE mode = 1 AND is_pro = 0 AND created BETWEEN ...";
$pro_query = "...WHERE s1.is_pro = 1 AND EXISTS (... s2.is_pro = 0 ...) AND s1.created BETWEEN ...";
$conversion_rate = ($pro_users / $free_users) * 100;
```

**Проблема:**
- Сравнивает Free активации в периоде с конверсиями в периоде
- Пользователь мог активировать Free год назад, а сконвертиться в этом периоде
- Знаменатель не включает таких пользователей
- Может быть > 100% если старые Free пользователи конвертируются

**Правильная формула:**
```
Conversion Rate = (Конверсии Free→Pro в периоде / Все Free пользователи на начало периода) * 100
```

**Исправление:**
```php
public function get_conversion_data($date_from, $date_to) {
    // Все Free пользователи на начало периода (активированные, не перешедшие на Pro)
    $free_users_at_start = $this->wpdb->get_var($this->wpdb->prepare(
        "SELECT COUNT(DISTINCT email) FROM {$this->stats_table} s1
         WHERE s1.mode = 1 AND s1.is_pro = 0 AND s1.created < %s
         AND NOT EXISTS (
             SELECT 1 FROM {$this->stats_table} s2
             WHERE s2.email = s1.email
             AND s2.is_pro = 1
             AND s2.created < %s
         )",
        $date_from, $date_from
    ));

    // Конверсии в периоде (первое появление is_pro=1)
    $conversions = $this->wpdb->get_var($this->wpdb->prepare(
        "SELECT COUNT(DISTINCT s1.email)
         FROM {$this->stats_table} s1
         WHERE s1.is_pro = 1
         AND s1.created BETWEEN %s AND %s
         AND EXISTS (
             SELECT 1 FROM {$this->stats_table} s2
             WHERE s2.email = s1.email
             AND s2.is_pro = 0
             AND s2.created < s1.created
         )
         AND NOT EXISTS (
             SELECT 1 FROM {$this->stats_table} s3
             WHERE s3.email = s1.email
             AND s3.is_pro = 1
             AND s3.created < %s
         )",
        $date_from, $date_to, $date_from
    ));

    $conversion_rate = $free_users_at_start > 0 ? ($conversions / $free_users_at_start) * 100 : 0;

    return array(
        'free_users_at_start' => $free_users_at_start,
        'conversions' => $conversions,
        'conversion_rate' => round($conversion_rate, 2)
    );
}
```

---

### 4. **Time to Conversion - неправильный JOIN**
**Локация:** `class-database.php:177-222`

**Текущий код:**
```php
WHERE s1.is_pro = 0 AND s1.mode = 1
AND s2.is_pro = 1 AND s2.mode IN (0,1)  // ⚠️ ПРОБЛЕМА
```

**Проблема:**
- `s2.mode IN (0,1)` включает mode=0 (статистику каждые 7 дней)
- Нужно найти ПЕРВУЮ запись с is_pro=1, а не любую статистику
- Сейчас может взять mode=0 через месяц после конверсии

**Исправление:**
```php
public function get_time_to_conversion() {
    $query = "SELECT
                s1.email,
                MIN(s1.created) as free_date,
                (SELECT MIN(created) FROM {$this->stats_table}
                 WHERE email = s1.email AND is_pro = 1) as pro_date,
                DATEDIFF(
                    (SELECT MIN(created) FROM {$this->stats_table}
                     WHERE email = s1.email AND is_pro = 1),
                    MIN(s1.created)
                ) as days_to_convert
              FROM {$this->stats_table} s1
              WHERE s1.is_pro = 0 AND s1.mode = 1
              AND EXISTS (
                  SELECT 1 FROM {$this->stats_table}
                  WHERE email = s1.email AND is_pro = 1
              )
              GROUP BY s1.email
              HAVING days_to_convert >= 0";

    // ... rest of the code
}
```

---

### 5. **Feature Conversion Rate - считает st_id вместо emails**
**Локация:** `class-database.php:341-381`

**Текущий код:**
```php
$converted_users = $this->wpdb->get_var($this->wpdb->prepare(
    "SELECT COUNT(DISTINCT d.st_id)  // ⚠️ st_id это ID записи, не пользователь
     FROM {$this->details_table} d
     JOIN {$this->stats_table} s ON d.st_id = s.id
     WHERE d.name = %s AND d.val_int > 0 AND s.is_pro = 1",
    $token_name
));
```

**Проблема:**
- st_id - это ID записи статистики, не уникальный пользователь
- Один пользователь имеет много записей (mode=0 каждые 7 дней)
- Метрика завышена в несколько раз

**Исправление:**
```php
public function get_feature_conversion_rates() {
    $features = array(
        'tokens_chatbots' => 'Chatbot',
        'tokens_postscreate' => 'Bulk Content',
        'tokens_workflow' => 'Workflow Builder'
    );

    $results = array();

    foreach ($features as $token_name => $feature_name) {
        // Уникальные пользователи, использовавшие фичу
        $total_users = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s.email)
             FROM {$this->details_table} d
             JOIN {$this->stats_table} s ON d.st_id = s.id
             WHERE d.name = %s AND d.val_int > 0",
            $token_name
        ));

        // Уникальные пользователи, использовавшие фичу И перешедшие на Pro
        $converted_users = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s.email)
             FROM {$this->details_table} d
             JOIN {$this->stats_table} s ON d.st_id = s.id
             WHERE d.name = %s AND d.val_int > 0
             AND EXISTS (
                 SELECT 1 FROM {$this->stats_table} s2
                 WHERE s2.email = s.email AND s2.is_pro = 1
             )",
            $token_name
        ));

        $conversion_rate = $total_users > 0 ? ($converted_users / $total_users) * 100 : 0;

        $results[] = array(
            'feature' => $feature_name,
            'total_users' => (int) $total_users,
            'converted_users' => (int) $converted_users,
            'conversion_rate' => round($conversion_rate, 2)
        );
    }

    return $results;
}
```

---

### 6. **User Segments - считает st_id вместо emails**
**Локация:** `class-analytics.php:328-381`

**Текущий код:**
```php
$query = "SELECT st_id, SUM(val_int) as total_tokens  // ⚠️ st_id
          FROM {$details_table}
          WHERE name LIKE 'tokens_%'
          GROUP BY st_id";
```

**Проблема:**
- Группирует по st_id (записям статистики), а не по пользователям
- Один пользователь = много записей = завышенные цифры

**Исправление:**
```php
private function calculate_user_segments() {
    global $wpdb;
    $stats_table = $wpdb->prefix . 'lms_stats';
    $details_table = $wpdb->prefix . 'lms_stats_details';

    // Последняя статистика для каждого пользователя
    $query = "SELECT s.email, SUM(d.val_int) as total_tokens
              FROM {$details_table} d
              JOIN {$stats_table} s ON d.st_id = s.id
              WHERE d.name LIKE 'tokens_%'
              AND s.id IN (
                  SELECT MAX(id) FROM {$stats_table}
                  WHERE mode = 0
                  GROUP BY email
              )
              GROUP BY s.email";

    $results = $wpdb->get_results($query, ARRAY_A);

    // ... rest segmentation logic
}
```

---

### 7. **API Provider Distribution - считает st_id**
**Локация:** `class-database.php:442-466`

**Текущий код:**
```php
$count = $this->wpdb->get_var($this->wpdb->prepare(
    "SELECT COUNT(DISTINCT st_id)  // ⚠️
     FROM {$this->details_table}
     WHERE name = %s AND val_int > 0",
    $key_name
));
```

**Проблема:**
- Та же проблема - st_id вместо email

**Исправление:**
```php
public function get_api_provider_distribution() {
    $providers = array(
        'apikey' => 'OpenAI',
        'gemini_api_key' => 'Gemini',
        'deep_seek_apikey' => 'DeepSeek'
    );

    $results = array();

    foreach ($providers as $key_name => $provider_name) {
        $count = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(DISTINCT s.email)
             FROM {$this->details_table} d
             JOIN {$this->stats_table} s ON d.st_id = s.id
             WHERE d.name = %s AND d.val_int > 0",
            $key_name
        ));

        $results[] = array(
            'provider' => $provider_name,
            'count' => (int) $count
        );
    }

    return $results;
}
```

---

### 8. **Churn By Plan - та же проблема что и общий churn**
**Локация:** `class-analytics.php:266-312`

**Проблема:**
- Делит деактивации на активации в одном периоде
- Правильно: деактивации / активные на начало периода

**Исправление:** Аналогично исправлению #1, но с фильтром по is_pro

---

### 9. **User Activity - неправильное определение плана**
**Локация:** `class-database.php:471-509`

**Текущий код:**
```php
MAX(CASE WHEN s.is_pro = 1 THEN 'Pro' ELSE 'Free' END) as plan
```

**Проблема:**
- MAX('Pro', 'Free') всегда вернет 'Pro' если хоть раз был Pro
- Что если пользователь был Pro, а потом downgrade на Free?
- Нужен план из последней записи

**Исправление:**
```php
public function get_user_activity($limit = 50, $offset = 0) {
    $query = $this->wpdb->prepare(
        "SELECT
            s.email,
            MIN(CASE WHEN s.mode = 1 THEN s.created END) as activated,
            (SELECT CASE WHEN is_pro = 1 THEN 'Pro' ELSE 'Free' END
             FROM {$this->stats_table}
             WHERE email = s.email
             ORDER BY created DESC LIMIT 1) as plan,
            MAX(s.created) as last_activity
         FROM {$this->stats_table} s
         GROUP BY s.email
         ORDER BY last_activity DESC
         LIMIT %d OFFSET %d",
        $limit,
        $offset
    );

    // ... rest of code
}
```

---

### 10. **Recent Conversions - тот же баг с mode IN (0,1)**
**Локация:** `class-database.php:227-254`

**Проблема:**
- `AND s2.mode IN (0,1)` включает статистику
- Нужна только первая запись Pro

**Исправление:** Аналогично исправлению #4

---

## 📊 Дополнительные проблемы

### 11. **Feature Usage - считает st_id**
**Локация:** `class-database.php:293-336`

```php
"SELECT COUNT(DISTINCT st_id) as user_count  // ⚠️
 FROM {$this->details_table}
 WHERE name = %s AND val_int > 0"
```

**Исправление:** JOIN с stats и COUNT(DISTINCT email)

---

### 12. **Multi-Feature Usage - считает st_id**
**Локация:** `class-analytics.php:386-431`

```php
"SELECT st_id, COUNT(DISTINCT name) as feature_count  // ⚠️
 FROM {$details_table}
 WHERE name LIKE 'tokens_%' AND val_int > 0
 GROUP BY st_id"
```

**Исправление:** JOIN с stats и GROUP BY email

---

## 🎯 Концептуальные проблемы

### Проблема: Понимание "Active Users"

Текущее определение неясно. Предлагаю:

**Вариант 1: Активные = Не деактивировали**
```sql
SELECT COUNT(DISTINCT email)
FROM wp_lms_stats
WHERE email IN (SELECT email FROM wp_lms_stats WHERE mode = 1)  -- активировали
AND email NOT IN (SELECT email FROM wp_lms_stats WHERE mode = 2) -- не деактивировали
```

**Вариант 2: Активные = Отправляли статистику недавно**
```sql
SELECT COUNT(DISTINCT email)
FROM wp_lms_stats
WHERE mode = 0
AND created >= DATE_SUB(NOW(), INTERVAL 14 DAY)  -- статистика за последние 14 дней
```

**Вариант 3: Комбинированный (рекомендую)**
```sql
SELECT COUNT(DISTINCT email)
FROM wp_lms_stats
WHERE mode = 0
AND created BETWEEN date_from AND date_to
AND email IN (SELECT email FROM wp_lms_stats WHERE mode = 1)
AND email NOT IN (
    SELECT email FROM wp_lms_stats s2
    WHERE mode = 2
    AND created > (SELECT MAX(created) FROM wp_lms_stats WHERE email = s2.email AND mode = 1)
)
```

---

## 🔧 Рекомендации по улучшению

### 1. **Добавить индексы**
```sql
CREATE INDEX idx_email_mode ON wp_lms_stats(email, mode);
CREATE INDEX idx_email_ispro ON wp_lms_stats(email, is_pro);
CREATE INDEX idx_created ON wp_lms_stats(created);
CREATE INDEX idx_stid_name ON wp_lms_stats_details(st_id, name);
```

### 2. **Кэширование**
- Метрики не меняются часто
- Добавить кэширование на 1 час для dashboard data
```php
$cache_key = 'aiwu_analytics_' . md5($date_from . $date_to . $plan . $feature);
$data = get_transient($cache_key);
if (false === $data) {
    $data = $analytics->get_dashboard_data(...);
    set_transient($cache_key, $data, HOUR_IN_SECONDS);
}
```

### 3. **Материализованная таблица для быстрых запросов**
Создать таблицу `wp_lms_stats_summary`:
```sql
CREATE TABLE wp_lms_stats_summary (
    email VARCHAR(255) PRIMARY KEY,
    first_activation DATE,
    last_activity DATE,
    current_plan ENUM('free', 'pro'),
    is_active TINYINT(1),
    total_tokens BIGINT,
    conversion_date DATE NULL,
    deactivation_date DATE NULL
);
```

Обновлять раз в час через CRON.

### 4. **Добавить валидацию метрик**
```php
// Churn rate не может быть > 100%
if ($churn_rate > 100) {
    error_log('Invalid churn rate: ' . $churn_rate);
    $churn_rate = 0;
}

// Conversion rate не может быть > 100%
if ($conversion_rate > 100) {
    error_log('Invalid conversion rate: ' . $conversion_rate);
    $conversion_rate = 0;
}
```

### 5. **Unit тесты**
Добавить PHPUnit тесты для всех расчетов метрик с тестовыми данными.

---

## 📈 Приоритизация исправлений

### P0 - Критично (исправить немедленно):
1. ✅ Churn Rate (#1)
2. ✅ Conversion Rate (#3)
3. ✅ Feature Conversion Rate (#5)

### P1 - Высокий приоритет:
4. ✅ Active Users (#2)
5. ✅ Time to Conversion (#4)
6. ✅ User Segments (#6)

### P2 - Средний приоритет:
7. ✅ API Provider Distribution (#7)
8. ✅ User Activity plan (#9)
9. ✅ Feature Usage (#11)

### P3 - Низкий приоритет (улучшения):
10. Добавить индексы
11. Кэширование
12. Unit тесты

---

## 📝 Итоговая таблица проблем

| # | Проблема | Файл | Строки | Критичность | Влияние |
|---|----------|------|--------|-------------|---------|
| 1 | Неправильный Churn Rate | class-database.php | 108-127 | 🔴 P0 | Метрика полностью неверна |
| 2 | Неправильный Active Users | class-database.php | 53-62 | 🟡 P1 | Занижена |
| 3 | Неправильный Conversion Rate | class-database.php | 67-103 | 🔴 P0 | Может быть > 100% |
| 4 | Time to Conversion mode bug | class-database.php | 177-222 | 🟡 P1 | Неточные данные |
| 5 | Feature Conversion st_id | class-database.php | 341-381 | 🔴 P0 | Завышена в N раз |
| 6 | User Segments st_id | class-analytics.php | 328-381 | 🟡 P1 | Завышена в N раз |
| 7 | API Provider st_id | class-database.php | 442-466 | 🟡 P1 | Завышена в N раз |
| 8 | Churn By Plan | class-analytics.php | 266-312 | 🔴 P0 | Аналогично #1 |
| 9 | User Activity plan | class-database.php | 471-509 | 🟠 P2 | Неточный план |
| 10 | Recent Conversions mode | class-database.php | 227-254 | 🟠 P2 | Неточная дата |
| 11 | Feature Usage st_id | class-database.php | 293-336 | 🟠 P2 | Завышена в N раз |
| 12 | Multi-Feature st_id | class-analytics.php | 386-431 | 🟠 P2 | Завышена в N раз |

---

## 🎬 Следующие шаги

1. **Немедленно:** Исправить P0 проблемы (#1, #3, #5, #8)
2. **На этой неделе:** Исправить P1 проблемы (#2, #4, #6, #7)
3. **В следующем спринте:** P2 проблемы + добавить индексы
4. **Backlog:** Unit тесты, кэширование, материализованная таблица

---

**Дата ревью:** 2025-12-09
**Ревьюер:** Claude (AI Code Reviewer)
**Версия:** AIWU Analytics 1.0.0
