<?php

namespace MargaTampu\LaravelTeamsLogging;

use Monolog\Logger;
use Monolog\Handler\AbstractProcessingHandler;

class LoggerHandler extends AbstractProcessingHandler
{
    /** @var string */
    private $url;

    /** @var string */
    private $style;

    /** @var string */
    private $name;

    /**
     * @param $url
     * @param int $level
     * @param string $name
     * @param bool $bubble
     */
    public function __construct($url, $level = Logger::DEBUG, $style, $name, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->url   = $url;
        $this->style = $style;
        $this->name  = $name;
    }

    /**
     * @param array $record
     *
     * @return LoggerMessage
     */
    protected function getMessage(array $record)
    {
        $loggerColour = new LoggerColour($record['level_name']);

        if ($this->style == 'card') {
            // Include context as facts to send to microsoft teams
            // Added Sent Date Info
            $facts = array_merge($record['context'], [[
                'name'  => 'Sent Date',
                'value' => date('D, M d Y H:i:s e'),
            ]]);

            return new LoggerMessage([
                'summary'    => $record['level_name'] . ($this->name ? ': ' . $this->name : ''),
                'themeColor' => (string) $loggerColour,
                'sections'   => [
                    [
                        'activityTitle'    => $this->name,
                        'activitySubtitle' => '<span style="color:#' . (string) $loggerColour . '">' . $record['level_name'] . '</span>',
                        'activityText'     => $record['message'],
                        'facts'            => $facts,
                        'markdown'         => true
                    ]
                ]
            ]);
        } else {
            return new LoggerMessage([
                'text'       => ($this->name ? $this->name . ' - ' : '') . '<span style="color:#' . (string) $loggerColour . '">' . $record['level_name'] . '</span>: ' . $record['message'],
                'themeColor' => (string) $loggerColour,
            ]);
        }
    }

    /**
     * @param array $record
     */
    protected function write(array $record)
    {
        $json = json_encode($this->getMessage($record));

        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json)
        ]);

        curl_exec($ch);
    }
}
