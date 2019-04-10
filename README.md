### Генерация турбостраниц через API Яндекса

Так как для малопосещаемых сайтов API Яндекса может не отдавать требуемые данные, то значит, что
подключать его будем для достаточно развитого ресурса, поэтому скачивание класса и установку из этого репозитория я пропущу.
```php
// Пример подключения

$host = "https:example.ru:443"; // Url сайта, для которого загружаются страницы
$api_url = 'https://api.webmaster.yandex.net/v4/user'; // Url запроса для получения user_id
$auth = 'KEY'; // Код авторизации (токен, сгенерированный для сайта в Яндекс.Вебмастере)
$debug = true; // Включение / выключение режима отладки DEBUG
$version  = "v4"; // Версия API из параметра url


// 1) Инициализация
$channel = new \YandexAPITurboPages\YandexAPI($host, $api_url, $auth, $debug, $version);

// 2) Получение ссылки
$link = $channel->getLink();

// 3) Генерация контента, пример с минимальными значениями
$myItems = // ... Массив с подстановочными значениями
$content = '<?xml version="1.0" encoding="UTF-8"?>' .
'<rss version="2.0" xmlns:yandex="http://news.yandex.ru" xmlns:turbo="http://turbo.yandex.ru"><channel>';
foreach ($myItems as $item) {
$content.= '<item turbo = "true" >' .
'<title >' . $item['title']  . '</title >' .
'<link >' . $item['link']  . '</link>' .
  '<turbo:content>' .
    '<![CDATA[' .
      '<header>' .
        '<h1>' . $item['h1']  . '</h1>' .
        '<menu>' .
          '<a href = "' . $item['menu_url_href']  . '" >' . $item['menu_url_name']  . '</a>' .             
        '</menu>' .
      '</header>' .
      '<p>' . $item['content'][0]  . '</p>' .
      '<p>' . $item['content'][1]  . '</p>' .     
    ']]>' .
  '</turbo:content>' .
'</item>';
    }
$content.= '</channel></rss>';

// 4) Добавить канал (c получением tack_id)
$tack = $channel->addContent($content);

// Дополнительно:

// Запросить информацию о добавленном канале (возвращает массив с информацией)
$channel_info = $channel->getChannelInfo($tack);

// Запросить информацию о добавленных каналах за месяц (возвращает массив с перечнем каналов)
$info = $channel->getChannelsInfoForPeriod();


```
