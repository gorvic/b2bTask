<?php

namespace DataProviders;

use
    Exception;

use function
    is_file,
    file_get_contents,
    json_decode,
    array_key_exists;

class CurrencyRates
{
    private string $base_currency = '';
    private array $rates = [];

    /**
     * Base currency code for rates calculation
     *
     * @return string
     */
    public function getBaseCurrency(): string
    {
        return $this->base_currency;
    }

    /**
     * Parse rates from JSON file
     *
     * It's easy point to extend it for diff gateways
     *
     * @param $rates_data
     * @return $this
     * @throws Exception
     */
    public function getRates($rates_data): self
    {
        if ( is_file($rates_data)) {
            $response = json_decode(file_get_contents($rates_data), true);
            $this->rates = $response['rates'];
            $this->base_currency = $response['base'];
        } else {
            throw new Exception("Cannot get rates.");
        }
        return $this;
    }

    /**
     * Get currency rate by its code
     *
     * @param string $code
     * @return float|null
     */
    public function getRate(string $code): ?float
    {
        return array_key_exists($code, $this->rates) && (float)$this->rates[$code] > 0
            ? $this->rates[$code]
            : null;
    }

    /**
     * Get currency cross rate
     *
     * @param string $fromCode
     * @param string $toCode
     * @return float|null
     */
    public function getConvertedRate(string $fromCode, string $toCode): ?float
    {
        $from = $this->getRate($fromCode);
        $to = $this->getRate($toCode);
        $base = $this->getRate($this->getBaseCurrency());
        return $from && $to ? $base / $from * $to : null;
    }

}