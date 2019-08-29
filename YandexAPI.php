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

    // Принимает:
    // host > url сайта типа https:example.com:443 Внимание(!) - без слешей. Порт для https - 443
    // url > вида "https://api.webmaster.yandex.net/v4/user" согласно документации API турбостраниц.
    // auth > "KEY" код авторизации (токен, сгенерированный для сайта в Яндекс.Вебмастере)
    // debug > true/false включение выключение режима отладки
    // api_version > версия API из параметра url
    // full_auth если начало заголовка авторизации отличается от "Authorization: OAuth"
    /**
     * YandexAPI constructor.
     * @param string $host
     * @param string $url
     * @param string $auth
     * @param bool $debug
     * @param string $api_version
     * @param null|string $full_auth
     */
    public function __construct($host, $url, $auth, $debug = true, $api_version = 'v4', $full_auth = null)
    {
        $this->url = $url;
        $this->host = $host;
        $this->debug = $debug;
        $this->api_version = $api_version;
        $this->headers[] = ($full_auth ? $full_auth : "Authorization: OAuth") . " " . $auth;
        $this->add_headers[] = ($full_auth ? $full_auth : "Authorization: OAuth") . " " . $auth;

        $this->get_user_id();
    }

    // Можно получить user_id
    public function getUserId()
    {
        return $this->user_id;
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

    private function arrContextOptions()
    {
        return array(
            'http' => array(
                'method' => 'GET',
                'ignore_errors' => true,
                'header' => implode("\r\n", $this->headers)
            ),
        );
    }

    private function arrContextAddOptions()
    {
        return array(
            'http' => array(
                'method' => 'POST',
                'ignore_errors' => true,
                'header' => implode("\r\n", $this->add_headers),
                'content' => $this->content
            ),
        );
    }

    // Запуск сформированного запроса с получением user_id
    private function get_user_id()
    {
        $result = json_decode($this->get_url($this->url, stream_context_create($this->arrContextOptions())), true);

        if (isset($result['user_id'])) {

            // Запрос прошёл успешно
            $this->user_id = intval($result['user_id']);
            return $this->user_id;

        }
        return $this->no_result($result, 'user_id');
    }

    private function get_url($url, $headers)
    {
        return file_get_contents($url, false, $headers);
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

    // Запуск сформированного запроса с получением ссылки на загрузку
    private function get_link_url()
    {
        $result = json_decode($this->get_url($this->link_url, stream_context_create($this->arrContextOptions())), true);

        if (isset($result['upload_address'])) {

            // Запрос прошёл успешно
            $this->upload_address = $result['upload_address'];
            $this->upload_address_valid_until = isset($result['valid_until']) ? $result['valid_until'] : "";
            return $this->upload_address;

        }
        return $this->no_result($result, 'upload_address');
    }

    // Добавление турбостраниц
    public function addContent($content)
    {
        $this->num_page++;
        if (!is_string($content)) print("\n" . "#" . $this->num_page . " Контент не является строковым значением." . "\n");
        $this->content = $content;
        return $this->add_content();

    }

    private function add_content()
    {
        $result = json_decode($this->get_url($this->upload_address, stream_context_create($this->arrContextAddOptions())), true);

        if (isset($result['task_id'])) {

            // Запрос прошёл успешно
            $this->task_ids[] = $result['task_id'];
            return end($this->task_ids);

        }
        return $this->no_result($result, 'task_id', "#" . $this->num_page . " ");
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

    private function get_channel_info()
    {

        $result = json_decode($this->get_url($this->get_task_url, stream_context_create($this->arrContextOptions())), true);

        if (isset($result['load_status'])) {
            return $result;
        }
        return $this->no_result($result, 'channel_info');
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

    private function get_channels_info()
    {
        $result = json_decode($this->get_url($this->get_task_url, stream_context_create($this->arrContextOptions())), true);

        return (count($result))? $result : false;

    }

    private function no_result($result, $name, $num = "")
    {
        if (!$result) {
            print "\n" . $num . "Отправка данных и получение $name: запрос не выполнен." . "\n";
        } else {
            print "\n" . $num . "Отправка данных и получение $name:  сообщение об ошибке " . "\n";
            print_r($result);
        }
        return false;
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


}
