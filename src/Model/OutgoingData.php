<?php

namespace Model;

use DataProviders\CurrencyRates;
use stdClass;

use function
    in_array,
    is_file,
    is_null,
    json_decode,
    json_encode,
    file_get_contents;
class OutgoingDefaults
{
    public static ?string $market = "ES";
    public static ?string $currency = "EUR";
}

class OutgoingData extends stdClass
{
    private ?array $rooms = null;

    public function __construct($data_file,
                                protected ?IncomingData $incomingData = null,
                                protected ?CurrencyRates $currencyRates = null  )
    {
        if ( is_file($data_file)) {
            $object = json_decode(file_get_contents($data_file), true);
            if (is_array($object)) {
                foreach ($object as $room) {
                    $this->rooms[] = new Room($room, $incomingData, $currencyRates);
                }
            }
        }
    }

    public function getRooms(): array
    {
        $rooms = [];
        foreach ($this->rooms as $room) {
            if ( in_array($room->getHotelCodeSupplier(), $this->incomingData->getAvailDestinations())) {
                $rooms[] = $room->getRoom();
            }
        }
        return $rooms;
    }
    public function __toString(): string
    {
        return json_encode($this->getRooms());
    }
}

class Room extends Castable
{
    protected ?string $id = null;
    protected ?string $hotelCodeSupplier = null;
    protected ?string $market = null;
    protected ?RoomPrice $price = null;

    public function __construct($object = null,
                                protected ?IncomingData $incomingData = null,
                                protected ?CurrencyRates $currencyRates = null  )
    {
        parent::__construct($object);
    }

    protected function setPrice(?array $price): void
    {
        $this->price = new RoomPrice($price, $this->incomingData, $this->currencyRates);
    }

    public function getHotelCodeSupplier(): ?string
    {
        return $this->hotelCodeSupplier;
    }

    public function getMarket(): string
    {
        return is_null($this->market) ? OutgoingDefaults::$market : $this->market;
    }

    public function getRoom(): array
    {
        return [
            "id" => $this->id,
            "hotelCodeSupplier" => $this->hotelCodeSupplier,
            "market" => $this->getMarket(),
            "price" => $this->price->getPrice()
        ];
    }
}

class RoomPrice extends Castable
{
    protected ?float $minimumSellingPrice = null;
    protected ?string $currency = null;
    protected ?float $net = null;
    protected ?float $selling_price = null;
    protected ?string $selling_currency = null;
    protected ?float $exchange_rate = null;
    protected ?float $markup = null;

    public function __construct($object = null,
                                protected ?IncomingData $incomingData = null,
                                protected ?CurrencyRates $currencyRates = null )
    {
        parent::__construct($object);

        $this->exchange_rate = $this->currency != $this->selling_currency
            ? $this->currencyRates->getConvertedRate($this->currency, $this->incomingData->getCurrency())
            : 1;
        $this->selling_price = (float) $this->net * ($this->incomingData->getMarkup() / 100 + 1) * (float) $this->exchange_rate;
        $this->markup = (float) $this->incomingData->getMarkup();
        $this->selling_currency = $this->incomingData->getCurrency();

    }

    public function getPrice(): array
    {
        return [
            "minimumSellingPrice" => $this->minimumSellingPrice,
            "currency" => $this->currency,
            "net" => $this->net,
            "selling_price" => $this->ceiling($this->selling_price, 2),
            "selling_currency" => $this->selling_currency,
            "markup" => $this->markup,
            "exchange_rate" => $this->exchange_rate
        ];
    }

    private function ceiling(float $value, ?int $decimals = null): ?float
    {
        $pow = $decimals ? 10 ** $decimals : 0;
        return $pow ? ceil((float)$value * $pow . '') / $pow : ceil($value . '');
    }



}