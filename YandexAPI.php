<?php

namespace YandexAPITurboPages;

class YandexAPI
{
    private $url = ""; // Url запроса для получения user_id
    private $host = ""; // Url сайта, для которого загружаются страницы
    private $debug = ""; // Режим отладки DEBUG
    private $user_id = 0; // Значение user_id
    private $num_page = 0; // Нумерация добавленных каналов
    private $content = ""; // Текущий контент (динамическое значение)
    private $link_url = ""; // Url запроса для получения upload_address
    private $task_ids = []; // Массив с возвращенными id добавляемых каналов
    private $get_task_url = ""; // Url запроса для получения информации о канале
    private $api_version = ""; // Используемая версия API
    private $upload_address = ""; // Ссылка на загрузку
    private $upload_address_valid_until = null;// Срок действия ссылки на загрузку
    private $headers = array("Content-Type: application/x-www-form-urlencoded");
    private $add_headers = array("Content-Type: application/rss+xml");
    private $curl = false; // Выполнять запросы через cURL
    private $error = false; // Ошибка во время выполнения

    /**
     * YandexAPI constructor.
     * @param string $host - url сайта типа https:example.com:443 Внимание(!) - без слешей. Порт для https - 443
     * @param string $url - вида "https://api.webmaster.yandex.net/v4/user" согласно документации API турбостраниц.
     * @param string $auth - "KEY" код авторизации (токен, сгенерированный для сайта в Яндекс.Вебмастере)
     * @param bool $debug - включение/выключение режима отладки
     * @param string $api_version - версия API из параметра url
     * @param null|string $full_auth - если начало заголовка авторизации отличается от "Authorization: OAuth"
     * @param bool $curl - если нет возможности включить allow_url_fopen на сервере, то необходимо запросы производить через cURL
     */
    public function __construct($host, $url, $auth, $debug = true, $api_version = 'v4', $full_auth = null, $curl = false)
    {
        $this->url = $url;
        $this->host = $host;
        $this->debug = $debug;
        $this->api_version = $api_version;
        $this->headers[] = ($full_auth ? $full_auth : "Authorization: OAuth") . " " . $auth;
        $this->add_headers[] = ($full_auth ? $full_auth : "Authorization: OAuth") . " " . $auth;
        $this->curl = $curl;
        $this->get_user_id();
    }

    // Можно получить user_id

    /**
     * Возвращает значение ID пользователя
     * @return int
     */
    public function getUserId()
    {
        return $this->user_id;
    }

    /**
     * Возвращает результат проверки на наличие ошибки при выполнении предыдущих запросов
     * @return bool
     */
    public function getErrorChecking()
    {
        return $this->error;
    }

    // Получить срок годности адреса загрузки после выполнения getLink()
    public function getValidUntil()
    {
        return $this->upload_address_valid_until;
    }

    // Получить номер добавленного текущим объектом канала
    public function getShannelNum()
    {
        return $this->num_page;
    }

    // Просмотр id отправленных текущим объектом страниц (массив)
    public function getTaskIds()
    {
        return $this->task_ids;
    }

    // Получение адреса с возможностью отправить собственный запрос
    public function getLink($link_url = null)
    {
        if (!empty($this->link_url)) return $this->link_url;

        $this->link_url = $link_url ? $link_url : "https://api.webmaster.yandex.net/" .
            $this->api_version . "/user/" . $this->user_id . "/hosts/" . $this->host .
            "/turbo/uploadAddress" . ($this->debug ? "?mode=DEBUG" : "");

        return $this->get_link_url();
    }

    // Добавление турбостраниц
    public function addContent($content)
    {
        $this->num_page++;
        if (!is_string($content)) print("\n" . "#" . $this->num_page . " Контент не является строковым значением." . "\n");
        $this->content = $content;
        return $this->add_content();

    }

    // Получение информации о каналах за месяц с возможностью отправить собственный запрос
    // с добавлением фильтров (например ["limit"=>10, "task_type_filter"=>"ALL"]) из указанных в документации API турбостраниц
    /**
     * @param null|string $get_task_url
     * @param array $filters
     * @return bool|array
     */
    public function getChannelsInfoForPeriod($get_task_url = null, $filters = [])
    {
        $this->get_task_url = $get_task_url ? $get_task_url : "https://api.webmaster.yandex.net/" .
            $this->api_version . "/user/" . $this->user_id . "/hosts/" . $this->host .
            "/turbo/tasks" . (count($filters) ? "?" . http_build_query($filters, null, '&') : "");

        return $this->get_channels_info();
    }

    // Получение информации о канале с возможностью отправить собственный запрос
    public function getChannelInfo($task_id = null, $get_task_url = null)
    {
        $task_id = $task_id ? $task_id : end($this->task_ids);

        $this->get_task_url = $get_task_url ? $get_task_url : "https://api.webmaster.yandex.net/" .
            $this->api_version . "/user/" . $this->user_id . "/hosts/" . $this->host .
            "/turbo/tasks/" . $task_id;

        return $this->get_channel_info();
    }

    /**
     * Принудительное обновление ссылки для загрузки
     *
     * @return bool|string
     */
    public function updateLinkUrl()
    {
        $this->link_url = null;
        return $this->getLink();
    }

    private function arrContextOptions()
    {
        return array(
            'http' => array(
                'method' => 'GET',
                'ignore_errors' => true,
                'header' => $this->curl ? $this->headers : implode("\r\n", $this->headers)
            ),
        );
    }

    private function arrContextAddOptions()
    {
        return array(
            'http' => array(
                'method' => 'POST',
                'ignore_errors' => true,
                'header' => $this->curl ? $this->add_headers : implode("\r\n", $this->add_headers),
                'content' => $this->content
            ),
        );
    }

    // Запуск сформированного запроса с получением user_id
    private function get_user_id()
    {
        $result = json_decode($this->get_url($this->url, $this->arrContextOptions()), true);

        if (isset($result['user_id'])) {

            // Запрос прошёл успешно
            $this->user_id = intval($result['user_id']);
            return $this->user_id;

        }
        return $this->no_result($result, 'user_id');
    }

    private function get_url($url, $headers)
    {
        if($this->curl){
            return $this->getUrlUsingCurl($url, $headers['http']);
        }
        return file_get_contents($url, false, stream_context_create($headers));
    }

    // Запуск сформированного запроса с получением ссылки на загрузку
    private function get_link_url()
    {
        $result = json_decode($this->get_url($this->link_url, $this->arrContextOptions()), true);

        if (isset($result['upload_address'])) {

            // Запрос прошёл успешно
            $this->upload_address = $result['upload_address'];
            $this->upload_address_valid_until = isset($result['valid_until']) ? $result['valid_until'] : "";
            return $this->upload_address;

        }
        return $this->no_result($result, 'upload_address');
    }

    private function add_content()
    {
        $result = json_decode($this->get_url($this->upload_address, $this->arrContextAddOptions()), true);

        if (isset($result['task_id'])) {

            // Запрос прошёл успешно
            $this->task_ids[] = $result['task_id'];
            return end($this->task_ids);

        }
        return $this->no_result($result, 'task_id', "#" . $this->num_page . " ");
    }

    private function get_channel_info()
    {

        $result = json_decode($this->get_url($this->get_task_url, $this->arrContextOptions()), true);

        if (isset($result['load_status'])) {
            return $result;
        }
        return $this->no_result($result, 'channel_info');
    }

    private function get_channels_info()
    {
        $result = json_decode($this->get_url($this->get_task_url, $this->arrContextOptions()), true);

        return (count($result))? $result : false;

    }

    private function no_result($result, $name, $num = "")
    {
        $this->error = true;
        if (!$result) {
            print "\n" . $num . "Отправка данных и получение $name: запрос не выполнен." . "\n";
        } else {
            print "\n" . $num . "Отправка данных и получение $name:  сообщение об ошибке " . "\n";
            print_r($result);
        }
        return false;
    }

    /**
     * Возвращает результат взаимодействия с API по всем запросам через cURL
     * @param string $url - на какой  url отправлять запрос
     * @param string|array $data - может использовать как массив, так и строку с параметрами
     * @return bool|string
     */
    protected function getUrlUsingCurl($url, $data)
    {
        if(!function_exists("curl_init")){
            print "\n" . "Не подключена библиотека libcurl (cURL) в PHP" . "\n";
            return false;
        };

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $data['method']);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $data['header']);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        if ($data['method'] == 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
        }
        if (isset($data['content'])) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data['content']);
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }


}
