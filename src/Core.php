<?php

namespace src;

use DataProviders\CurrencyRates;
use Model\IncomingData;
use Exception;

use Model\OutgoingData;
use function
    simplexml_load_string,
    file_get_contents;

/**
 * Just a simple Singleton class
 * communicates with Task App
 */
class Core
{
    private static ?Core $instance = null;
    private ?CurrencyRates $ratesRequest;
    private ?IncomingData $varOcg;

    /**
     * @throws Exception
     */
    private function init(): void
    {
        $this->varOcg = $this->initIncomingData();
        $errors = $this->varOcg->checkProperties();
        if (count($errors)) {
            $this->makeErrorResponseAndFinish($errors);
        } else {
            $this->ratesRequest = $this->initRates();
        }
    }

    /**
     * Add error XML to output and finish execution
     * @param array $errors
     * @return void
     */
    private function makeErrorResponseAndFinish(array $errors): void
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>\n';
        foreach ($errors as $error) {
            $out .= "<applicationErrors>\n<code>5</code>\n<type>5</type>\n".
                "<description>{$error}</description>\n".
                "<httpStatusCode>0</httpStatusCode>\n</applicationErrors>";
        }
        die($out);
    }

    /**
     * __define-ocg__
     *
     * Parse sample POST data from wellformed XML file
     *
     * @return IncomingData|null
     * @throws Exception
     */
    private function initIncomingData(): ?IncomingData
    {
        $post_data = __DIR__ . '/../bin/in.xml';
        if ( is_file($post_data)) {
            $parsed = simplexml_load_string(file_get_contents($post_data), 'SimpleXMLElement', LIBXML_NOCDATA);
            return new IncomingData($parsed);
        } else {
            throw new Exception("Cannot obtain incoming data.");
        }
    }

    /**
     * Getter for prepared and validated  POST Incoming Data
     *
     * @return IncomingData
     */
    public function getVarOcg(): IncomingData
    {
        return $this->varOcg;
    }

    /**
     * Parse sample Currency Rates JSON file
     *
     * (rates from api.exchangeratesapi.io)
     *
     * @return CurrencyRates
     * @throws Exception
     */
    private function initRates(): CurrencyRates
    {
        return (new CurrencyRates)->getRates(__DIR__ . '/../bin/rates.json');
    }


    /**
     * Getter for Currency Rates
     *
     * @return CurrencyRates
     */
    public function getCurrencyRates(): CurrencyRates
    {
        return $this->ratesRequest;
    }

    /**
     * Return plain text JSON Response
     * calculation are based on Incoming Data
     *
     * @return string
     */
    public function getResponse(): string
    {
        return strval(new OutgoingData(
            __DIR__ . '/../bin/out.json',
            $this->getVarOcg(),
            $this->getCurrencyRates()));
    }


    /**
     * Singleton runner
     *
     * @return self
     */
    public static function run(): self
    {
        if (!self::$instance) {
            self::$instance = new static();

        }
        return self::$instance;
    }

    /**
     * @throws Exception
     */
    protected function __construct()
    {
        $this->init();
    }


    /**
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }


    protected function __clone() {}
}