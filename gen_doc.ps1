$outputPath = "C:\xampp\htdocs\w26\docreg-5MI0600358-2MI0600298\documentation.docx"
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$doc  = $word.Documents.Add()
$sel  = $word.Selection

# Page setup - A4
$doc.PageSetup.PaperSize     = 9
$doc.PageSetup.TopMargin     = $word.CentimetersToPoints(2.5)
$doc.PageSetup.BottomMargin  = $word.CentimetersToPoints(2.5)
$doc.PageSetup.LeftMargin    = $word.CentimetersToPoints(3.0)
$doc.PageSetup.RightMargin   = $word.CentimetersToPoints(2.0)

# Constants
$Center   = 1; $Left = 0; $Justify = 3
$Bold     = $true; $NoBold = $false
# Word color (BGR long): wdColorRed=255, wdColorAutomatic=16777215
$RedColor  = 255
$AutoColor = 16777215

function NL  { $sel.TypeParagraph() }
function PB  { $sel.InsertBreak(7) }   # wdPageBreak = 7

function Normal {
    try { $sel.Style = $doc.Styles["Normal"] } catch {}
    $sel.ParagraphFormat.Alignment = $Justify
    $sel.Font.Bold  = $NoBold
    $sel.Font.Size  = 12
    $sel.Font.Color = $AutoColor
    $sel.Font.Name  = "Calibri"
}

function H1($text) {
    try { $sel.Style = $doc.Styles["Heading 1"] } catch {
        Normal; $sel.Font.Bold = $Bold; $sel.Font.Size = 14
    }
    $sel.TypeText($text); NL; Normal
}

function T($text)   { $sel.TypeText($text) }
function THL($text) { $sel.Font.Color = $RedColor; $sel.Font.Bold = $Bold; $sel.TypeText($text); $sel.Font.Color = $AutoColor; $sel.Font.Bold = $NoBold }

function P($text)   { Normal; $sel.TypeText($text); NL }
function PHL($text) { Normal; THL $text; NL }

function Bullet($text) {
    try { $sel.Style = $doc.Styles["List Bullet"] } catch { Normal; T "  - " }
    $sel.TypeText($text); NL; Normal
}

function Code($text) {
    Normal
    $sel.Font.Name = "Courier New"; $sel.Font.Size = 10
    $sel.TypeText($text); NL
    Normal
}

function SectionSep { NL }

# =====================================================================
# COVER PAGE
# =====================================================================
Normal
$sel.ParagraphFormat.Alignment = $Center
$sel.Font.Size = 14; $sel.Font.Bold = $Bold
NL; NL
T "Софийски университет ""Свети Климент Охридски"""
NL
$sel.Font.Size = 12; $sel.Font.Bold = $NoBold
T "Факултет по математика и информатика"
NL; NL
T "Web технологии, летен семестър "; THL "20__/20__ г."
NL; NL; NL
$sel.Font.Size = 16; $sel.Font.Bold = $Bold
T "Документация на курсов проект"
NL
T "на тема"
NL; NL
$sel.Font.Size = 14
T "w26 – Система за Входиране на Документи"
NL; NL; NL
$sel.Font.Size = 12; $sel.Font.Bold = $NoBold
T "Специалност "; THL "[СПЕЦИАЛНОСТ]"; T ", "; THL "[X]"; T " курс, "; THL "[X]"; T " група"
NL; NL
T "Изготвили:"; NL
THL "[ИМЕ НА СТУДЕНТ 1]"; T ", 5MI0600358"; NL
THL "[ИМЕ НА СТУДЕНТ 2]"; T ", 2MI0600298"; NL
NL
T "Преподавател: проф. д-р Милен Петров"; NL
NL; NL
T "София, "; THL "[ДД.ММ.ГГГГ]"; T " г."

PB

# =====================================================================
# TABLE OF CONTENTS
# =====================================================================
$sel.ParagraphFormat.Alignment = $Center
$sel.Font.Bold = $Bold; $sel.Font.Size = 14
T "СЪДЪРЖАНИЕ"; NL; NL
Normal; $sel.ParagraphFormat.Alignment = $Left
$toc = @(
    "Условие",                                                                "2"
    "Въведение – извличане на изисквания",                                    "3"
    "Теория – анализ и проектиране на решението",                             "4"
    "Използвани технологии",                                                  "6"
    "Инсталация, настройки и DevOps",                                        "7"
    "Кратко ръководство на потребителя",                                      "9"
    "Примерни данни",                                                         "11"
    "Описание на програмния код",                                             "12"
    "Приноси на студентите, ограничения и бъдещо разширение",                 "15"
    "Какво научих",                                                           "16"
    "Използвани източници",                                                   "16"
)
for ($i = 0; $i -lt $toc.Count; $i += 2) {
    Normal; $sel.ParagraphFormat.Alignment = $Left
    T ($toc[$i]); T (" .............. " + $toc[$i+1]); NL
}

PB

# =====================================================================
# 1. УСЛОВИЕ
# =====================================================================
H1 "Условие"
PHL "[[Поставете тук оригиналния текст на условието на проекта, зададено от преподавателя.]]"
SectionSep
P "Основни изисквания, реализирани в проекта:"
Bullet "Регистриране на входящи документи с уникален входящ номер (формат ВХ-ГГГГ-ХХХХ)"
Bullet "Генериране на QR код за бързо проследяване на всеки документ"
Bullet "Уникален код за достъп за анонимно проследяване без акаунт"
Bullet "Управление на статуси: Чакащ, В обработка, Обработен, Паузиран, Архивиран"
Bullet "Приоритизиране на документи: Нормален и Висок приоритет"
Bullet "Категоризация на документи по отдели с назначени отговорници"
Bullet "Качване на файлове в PDF и ZIP формат (максимум 50 MB)"
Bullet "Криптиране на чувствителни документи с AES-256-CBC"
Bullet "Поддръжка на роли: Администратор и Обикновен потребител"
Bullet "Докеризирано разгръщане с конфигурация чрез .env файл"

# =====================================================================
# 2. ВЪВЕДЕНИЕ
# =====================================================================
H1 "Въведение – извличане на изисквания"
P "Системата поддържа три типа потребители, всеки с определени функционалности:"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Анонимен потребител (без акаунт)"; NL; $sel.Font.Bold = $NoBold
Bullet "Входиране на нов документ (заглавие, описание, категория, данни за подател и файл)"
Bullet "Получаване на входящ номер, код за достъп и QR код след успешно входиране"
Bullet "Проследяване на документ чрез входящ номер и код за достъп"
Bullet "Преглед на статус и история на статусните промени"
Bullet "Сканиране на QR код за директен достъп до страницата за проследяване"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Обикновен потребител (влязъл в системата)"; NL; $sel.Font.Bold = $NoBold
Bullet "Всичко от анонимния потребител"
Bullet "Преглед на документи, входирани лично от него (докато е бил влязъл)"
Bullet "Преглед на документи от категориите, за които е назначен като отговорник"
Bullet "Обобщена статистика (Чакащи, В обработка, Обработени, Паузирани, Приоритетни)"
Bullet "Филтриране по статус и приоритет, търсене по текст"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Администратор"; NL; $sel.Font.Bold = $NoBold
Bullet "Преглед на всички документи в системата"
Bullet "Промяна на статус и приоритет на документи едновременно"
Bullet "Преглед на пълната история на статусните промени"
Bullet "Създаване и управление на потребителски акаунти"
Bullet "Активиране и деактивиране на потребители"
Bullet "Нулиране на пароли на потребители"

# =====================================================================
# 3. ТЕОРИЯ
# =====================================================================
H1 "Теория – анализ и проектиране на решението"
P "В хода на разработването бяха приложени следните подходи и техники:"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Архитектура без фреймуърк (Vanilla PHP)"; NL; $sel.Font.Bold = $NoBold
P "Проектът е реализиран на чист PHP 8.2 без използване на фреймуърк. Помощните класове DB, Auth и функциите в includes/ осигуряват необходимата абстракция и разделение на отговорностите, без зависимост от външни пакети. Всички PHP файлове се включват явно чрез require_once."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "PDO – абстракция на база данни"; NL; $sel.Font.Bold = $NoBold
P "Комуникацията с базата данни се осъществява изцяло чрез PHP Data Objects (PDO) с MySQL/MariaDB. Класът DB имплементира обвивка над PDO с методи query(), one(), all(), insert() и управление на настройки. Всички заявки използват prepared statements, което елиминира риска от SQL инжекция."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Сесийна автентикация и CSRF защита"; NL; $sel.Font.Bold = $NoBold
P "Автентикацията е реализирана чрез PHP сесии. Класът Auth управлява вход, изход и проверка на роли. При вход се регенерира ID-то на сесията (session_regenerate_id). Всяка POST заявка изисква CSRF токен, генериран и верифициран при всяка форма."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Генериране на QR кодове (чист PHP, без библиотеки)"; NL; $sel.Font.Bold = $NoBold
P "QR кодовете се генерират без използване на външни библиотеки. Класът QRGenerator имплементира пълен QR код генератор (Model 2, версии 1-10) на базата на стандарта ISO/IEC 18004. Използва GF(256) аритметика за Reed-Solomon корекция на грешки (ниво M) и рендира PNG изображения чрез GD разширението на PHP."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Криптиране на документи (AES-256-CBC)"; NL; $sel.Font.Bold = $NoBold
P "Системата поддържа криптиране на чувствителни документи с алгоритъм AES-256-CBC. Ключовете се разделят на части, съхранявани при различни притежатели, което осигурява многостранен контрол на достъпа."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Одит лог за достъп"; NL; $sel.Font.Bold = $NoBold
P "Всеки достъп до документ се записва в таблица access_log с информация за потребителя, IP адреса, типа на достъпа и времето. Пълната история на статусните промени се пази в таблица document_history."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Конфигурация чрез .env файл"; NL; $sel.Font.Bold = $NoBold
P "Всички настройки (база данни, URL, пътища, максимален размер на файл) са изнесени в .env файл и се зареждат от config/config.php. Това осигурява лесна смяна на средата (локална разработка, Docker, облак) без промяна на кода."

# =====================================================================
# 4. ТЕХНОЛОГИИ
# =====================================================================
H1 "Използвани технологии"
$tech = @(
    "PHP 8.2",                 "Сървърен език за програмиране"
    "MySQL / MariaDB 10.11",   "Релационна база данни"
    "Apache HTTP Server 2.4",  "Уеб сървър (XAMPP / Docker образ php:8.2-apache)"
    "HTML5 / CSS3",            "Клиентска страна, без CSS фреймуърк"
    "JavaScript (ES6+)",       "Vanilla JS, без библиотеки"
    "Docker Engine 24+",       "Контейнеризация (Dockerfile + docker-compose.yml)"
    "Docker Compose v2",       "Оркестрация: app, db (MariaDB), phpMyAdmin"
    "GD (PHP extension)",      "Генериране на PNG изображения за QR кодовете"
    "PDO (PHP extension)",     "Абстракция за достъп до базата данни"
    "XAMPP",                   "Локална среда за разработка (Windows)"
)
for ($i = 0; $i -lt $tech.Count; $i += 2) {
    Normal; $sel.ParagraphFormat.Alignment = $Left
    $sel.Font.Bold = $Bold;   T ($tech[$i] + " – "); $sel.Font.Bold = $NoBold
    T $tech[$i+1]; NL
}

# =====================================================================
# 5. ИНСТАЛАЦИЯ
# =====================================================================
H1 "Инсталация, настройки и DevOps"
Normal; $sel.Font.Bold = $Bold; T "А. Инсталация чрез XAMPP (локална разработка)"; NL; $sel.Font.Bold = $NoBold
SectionSep
$xampp = @(
    "Разархивирайте проекта в директория C:\xampp\htdocs\w26\docreg-5MI0600358-2MI0600298"
    "Стартирайте Apache и MySQL от XAMPP Control Panel"
    "Отворете phpMyAdmin (http://localhost/phpmyadmin) и създайте база данни с име docreg"
    "Изпълнете скрипта sql/schema.sql (phpMyAdmin → Import → изберете файла)"
    "Конфигурирайте .env файла в корена на проекта (вижте примера по-долу)"
    "Намерете локалния IP адрес на машината (команда ipconfig в Command Prompt)"
    "Навигирайте до http://[LOCAL_IP]/w26/docreg-5MI0600358-2MI0600298"
)
for ($i = 0; $i -lt $xampp.Count; $i++) {
    Normal; $sel.ParagraphFormat.Alignment = $Left
    T (($i+1).ToString() + ". " + $xampp[$i]); NL
}
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Пример за .env (XAMPP):"; NL; $sel.Font.Bold = $NoBold
$code1 = @(
    "DB_HOST=localhost"
    "DB_PORT=3306"
    "DB_NAME=docreg"
    "DB_USER=root"
    "DB_PASS="
    "DB_ROOT_PASS=root"
    "APP_BASE_URL=http://10.108.x.x    # Заменете с вашия локален IP"
    "APP_BASE_PATH=/w26/docreg-5MI0600358-2MI0600298"
)
foreach ($line in $code1) { Code $line }
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Б. Инсталация чрез Docker"; NL; $sel.Font.Bold = $NoBold
SectionSep
$docker = @(
    "Инсталирайте Docker Desktop от https://www.docker.com/products/docker-desktop"
    "Стартирайте Docker Desktop и изчакайте да се инициализира"
    "Конфигурирайте .env файла с Docker-специфични стойности (вижте примера по-долу)"
    "Отворете терминал в директорията на проекта"
    "Изпълнете командата: docker compose up --build"
    "Навигирайте до http://localhost:8080"
    "(Опционално) phpMyAdmin е достъпен на http://localhost:8081"
)
for ($i = 0; $i -lt $docker.Count; $i++) {
    Normal; $sel.ParagraphFormat.Alignment = $Left
    T (($i+1).ToString() + ". " + $docker[$i]); NL
}
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Пример за .env (Docker):"; NL; $sel.Font.Bold = $NoBold
$code2 = @(
    "DB_HOST=db              # Задължително 'db' – името на Docker услугата"
    "DB_PORT=3306"
    "DB_NAME=docreg"
    "DB_USER=root"
    "DB_PASS=secret"
    "DB_ROOT_PASS=secret"
    "APP_BASE_URL=http://localhost:8080  # При деплой: публичен URL"
    "APP_BASE_PATH=/                     # В Docker приложението е на корена"
)
foreach ($line in $code2) { Code $line }
SectionSep
P "При деплой в облак (например AWS EC2), стойностите APP_BASE_URL и APP_BASE_PATH се актуализират в .env файла без промяна на кода. Схемата на базата данни се прилага автоматично от Docker при първо стартиране чрез монтирания файл sql/schema.sql."

# =====================================================================
# 6. РЪКОВОДСТВО НА ПОТРЕБИТЕЛЯ
# =====================================================================
H1 "Кратко ръководство на потребителя"
P "Следната точка описва потребителския интерфейс и начина на работа с него."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "6.1 Вход в системата"; NL; $sel.Font.Bold = $NoBold
P "Администраторите и обикновените потребители влизат в системата чрез страницата за вход (/public/login.php). Анонимните потребители могат да входират и проследяват документи без акаунт."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "6.2 Входиране на документ"; NL; $sel.Font.Bold = $NoBold
P "Страницата за входиране е разделена на два панела, наредени един до друг. Левият панел съдържа полета за детайли на документа: заглавие (задължително), описание и категория. Десният панел съдържа информация за подателя: имена (задължително), email и телефон, и поле за прикачване на файл (PDF или ZIP, до 50 MB). Бутонът ""Входирай документа"" е центриран под двата панела."
P "При успешно входиране системата генерира уникален входящ номер (формат ВХ-ГГГГ-ХХХХ), код за достъп и QR код. Тези данни се показват на потребителя и трябва да бъдат запазени за бъдещо проследяване. QR кодът може да бъде сканиран от мобилно устройство в същата мрежа."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "6.3 Проследяване на документ"; NL; $sel.Font.Bold = $NoBold
P "Страницата за проследяване (/public/track.php) позволява на всеки потребител да провери статуса на документ чрез въвеждане на входящия номер и кода за достъп. Показват се текущият статус, приоритетът и пълната хронологична история на промените."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "6.4 Моите документи (Обикновен потребител)"; NL; $sel.Font.Bold = $NoBold
P "Влезлите в системата обикновени потребители имат достъп до страницата ""Моите Документи"", където виждат документите, входирани лично от тях, и документите от категориите, за които са назначени като отговорници. Страницата предоставя обобщена статистика (Чакащи, В обработка, Обработени, Паузирани, Приоритетни) и филтри по статус, приоритет и ключова дума."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "6.5 Административно табло (Администратор)"; NL; $sel.Font.Bold = $NoBold
P "Администраторите имат достъп до две секции в навигацията:"
SectionSep
Normal; $sel.Font.Italic = $true; $sel.Font.Bold = $Bold; T "Администрация"; $sel.Font.Italic = $false; $sel.Font.Bold = $NoBold; NL
P "Показва обобщена статистика (Чакащи, В обработка, Обработени, Паузирани, Приоритетни) и таблица с последните документи в системата. При преглед на конкретен документ (бутон ""Преглед"") администраторът вижда пълните данни и може да промени статуса и приоритета едновременно от един комбиниран падащ панел."
SectionSep
Normal; $sel.Font.Italic = $true; $sel.Font.Bold = $Bold; T "Потребители"; $sel.Font.Italic = $false; $sel.Font.Bold = $NoBold; NL
P "Позволява добавяне на нови потребители с избор на роля (Обикновен потребител или Администратор), деактивиране/активиране на съществуващи акаунти и нулиране на пароли."

# =====================================================================
# 7. ПРИМЕРНИ ДАННИ
# =====================================================================
H1 "Примерни данни"
P "За тестване на системата базата данни е предварително попълнена с начални данни чрез скрипта sql/schema.sql."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Администраторски акаунт по подразбиране:"; NL; $sel.Font.Bold = $NoBold
Code "Потребителско име: admin"
Code "Парола:           Admin1234!"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Предварително дефинирани категории:"; NL; $sel.Font.Bold = $NoBold
Bullet "Отдел Студенти – документи за студентски въпроси, уверения, преводи"
Bullet "Учебен отдел Магистри – документи за магистърски програми"
Bullet "Кандидат-студенти – документи от кандидат-студенти"
Bullet "Сесия – документи свързани с изпитни сесии"
Bullet "Без категория – некатегоризирани документи"
SectionSep
P "За пълно тестване на функционалностите е препоръчително да се създаде поне един обикновен потребител чрез административния панел (Потребители → Нов потребител) и да му се зададе категория."

# =====================================================================
# 8. ОПИСАНИЕ НА ПРОГРАМНИЯ КОД
# =====================================================================
H1 "Описание на програмния код"
P "Проектът е организиран в следната файлова структура:"
SectionSep
$fs = @(
    "docreg-5MI0600358-2MI0600298/"
    "|-- config/"
    "|   `-- config.php              # Зарежда .env, дефинира константи"
    "|-- includes/"
    "|   |-- auth.php                # Клас Auth - сесия, роли, вход/изход"
    "|   |-- db.php                  # Клас DB - PDO обвивка"
    "|   |-- functions.php           # Помощни функции (CSRF, flash, redirect)"
    "|   |-- layout.php              # layoutHead/Nav/Foot helper-и"
    "|   |-- crypto/"
    "|   |   `-- CryptoHelper.php    # AES-256-CBC криптиране"
    "|   `-- qr/"
    "|       `-- QRGenerator.php     # Pure PHP QR код генератор"
    "|-- public/"
    "|   |-- admin/"
    "|   |   |-- index.php           # Административно табло"
    "|   |   |-- document_view.php   # Преглед и управление на документ"
    "|   |   |-- users.php           # Управление на потребители"
    "|   |   |-- documents.php       # Всички документи с филтри"
    "|   |   |-- archive.php         # Архивирани документи"
    "|   |   |-- categories.php      # Управление на категории"
    "|   |   `-- statistics.php      # Статистика"
    "|   |-- officer/"
    "|   |   |-- index.php           # Моите Документи"
    "|   |   `-- document_view.php   # Преглед на документ"
    "|   |-- assets/"
    "|   |   |-- css/style.css       # Глобални стилове"
    "|   |   `-- js/main.js          # Клиентски JavaScript"
    "|   |-- login.php               # Страница за вход"
    "|   |-- logout.php              # Изход от системата"
    "|   |-- submit.php              # Входиране на документ"
    "|   |-- track.php               # Проследяване на документ"
    "|   `-- qr_image.php            # Сервиране на QR изображения"
    "|-- sql/"
    "|   `-- schema.sql              # Схема на базата и начални данни"
    "|-- uploads/"
    "|   |-- docs/                   # Качени документи (PDF/ZIP)"
    "|   `-- qr/                     # Генерирани QR изображения (PNG)"
    "|-- .env                        # Конфигурационен файл"
    "|-- Dockerfile                  # Docker образ (php:8.2-apache + GD + PDO)"
    "|-- docker-compose.yml          # Услуги: app, db, phpmyadmin"
    "`-- apache.conf                 # Apache VirtualHost конфигурация"
)
Normal; $sel.Font.Name = "Courier New"; $sel.Font.Size = 10; $sel.ParagraphFormat.Alignment = $Left
foreach ($line in $fs) { T $line; NL }
Normal; SectionSep

Normal; $sel.Font.Bold = $Bold; T "Ключови класове:"; NL; $sel.Font.Bold = $NoBold; SectionSep

Normal; $sel.Font.Bold = $Bold; T "DB (includes/db.php)"; NL; $sel.Font.Bold = $NoBold
P "Статичен клас, обвиващ PDO връзката към MySQL. Осигурява единствена инстанция (singleton) и методи: query() за изпълнение с prepared statements, one() за единичен резултат, all() за списък, insert() с lastInsertId(), setting() и setSetting() за работа с таблицата с настройки."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Auth (includes/auth.php)"; NL; $sel.Font.Bold = $NoBold
P "Статичен клас за управление на автентикацията. login() верифицира потребителя с password_verify() и стартира сесия с регенериран ID. logout() унищожава сесията. Методи check(), role(), id(), fullName(), isAdmin(), isOfficer(), requireLogin(), requireAdmin() осигуряват достъп и контрол на роли."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "QRGenerator (includes/qr/QRGenerator.php)"; NL; $sel.Font.Bold = $NoBold
P "Пълен QR код генератор, реализиран в чист PHP без външни зависимости. Поддържа Model 2, версии 1-10, байтово кодиране. Имплементира GF(256) аритметика, Reed-Solomon корекция (ниво M), finder/timing/alignment патерни, форматна информация и маскиране (маска 0). Рендира PNG изображения чрез GD разширението."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "functions.php (includes/functions.php)"; NL; $sel.Font.Bold = $NoBold
P "Глобални помощни функции: csrf() генерира CSRF токен, verifyCsrf() го проверява, flash() и getFlash() управляват еднократни съобщения, redirect() пренасочва с Location header, h() екранира HTML, generateIncomingNumber() и generateAccessCode() генерират входящ номер и код за достъп, handleDocumentUpload() обработва качения файл."
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Схема на базата данни (sql/schema.sql)"; NL; $sel.Font.Bold = $NoBold
P "Базата данни docreg съдържа следните таблици:"
Bullet "users – потребителски акаунти (username, email, password_hash, full_name, role, is_active)"
Bullet "categories – категории с назначен отговорник (officer_user_id)"
Bullet "documents – входирани документи (incoming_number, title, status, priority, access_code, submitted_by_user_id, qr_filename, is_encrypted)"
Bullet "document_history – хронология на статусните промени"
Bullet "access_log – одит лог на достъпите до документи"
Bullet "document_encryptions и encryption_key_parts – данни за криптиране"
Bullet "settings – системни настройки (брояч на документи, заглавие на сайта)"

# =====================================================================
# 9. ПРИНОСИ
# =====================================================================
H1 "Приноси на студентите, ограничения и възможности за бъдещо разширение"
Normal; $sel.Font.Bold = $Bold; T "Приноси:"; NL; $sel.Font.Bold = $NoBold
PHL "[[Попълнете тук кой от студентите какво е реализирал – разпределение на задачите и индивидуален принос.]]"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Ограничения:"; NL; $sel.Font.Bold = $NoBold
Bullet "При локална инсталация QR кодовете са достъпни само от устройства в същата мрежа"
Bullet "Липсва изпращане на email известия при промяна на статус"
Bullet "Няма реално-времево обновяване на интерфейса (без WebSockets или SSE)"
Bullet "Криптирането изисква ръчна координация между притежателите на части от ключа"
Bullet "Нямa поддръжка на множество файлове за един документ"
SectionSep
Normal; $sel.Font.Bold = $Bold; T "Възможности за бъдещо разширение:"; NL; $sel.Font.Bold = $NoBold
Bullet "Email известия при промяна на статус (интеграция с SMTP)"
Bullet "Реално-времени нотификации (WebSockets или Server-Sent Events)"
Bullet "Мобилна версия / Progressive Web App"
Bullet "Разширена статистика с PDF/Excel експорт"
Bullet "Прилагане на модерен тъмен дизайн (вдъхновен от Linear, Vercel)"
Bullet "Интеграция с Active Directory/LDAP за корпоративна автентикация"
Bullet "Поддръжка на множество файлове към един документ"

# =====================================================================
# 10. КАКВО НАУЧИХ
# =====================================================================
H1 "Какво научих"
PHL "[[Попълнете тук лични изводи и умения, придобити по време на разработката на проекта – технически и нетехнически аспекти.]]"

# =====================================================================
# 11. ИЗТОЧНИЦИ
# =====================================================================
H1 "Използвани източници"
Normal; $sel.ParagraphFormat.Alignment = $Left
$sources = @(
    "PHP Documentation – https://www.php.net/docs.php"
    "MySQL Reference Manual – https://dev.mysql.com/doc/"
    "Docker Documentation – https://docs.docker.com/"
    "Apache HTTP Server Documentation – https://httpd.apache.org/docs/"
    "MDN Web Docs (HTML/CSS/JS) – https://developer.mozilla.org/"
    "ISO/IEC 18004 – QR Code Standard (основа за реализацията на QRGenerator.php)"
)
foreach ($s in $sources) { T $s; NL }

# =====================================================================
# ПОДПИСИ
# =====================================================================
PB
Normal; $sel.ParagraphFormat.Alignment = $Center
NL; NL; NL; NL
T "Предал (подпис): …………………………………."
NL
T "/"; THL "[ИМЕ НА СТУДЕНТ 1]"; T ", 5MI0600358/"
NL; NL; NL
T "Предал (подпис): …………………………………."
NL
T "/"; THL "[ИМЕ НА СТУДЕНТ 2]"; T ", 2MI0600298/"
NL; NL; NL
T "Приел (подпис): …………………………………."
NL
T "/проф. д-р Милен Петров/"

# =====================================================================
# SAVE
# =====================================================================
$doc.SaveAs2($outputPath, 16)
$doc.Close($false)
$word.Quit()
[System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) | Out-Null
[System.GC]::Collect()
[System.GC]::WaitForPendingFinalizers()
Write-Output "Saved: $outputPath"
