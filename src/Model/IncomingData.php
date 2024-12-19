<?php

namespace Model;

use DateInterval;
use DateTime;
use SimpleXMLElement;

use function intval, strval;
use function is_object, is_null, is_numeric, is_int;
use function property_exists, method_exists;
use function ucfirst, lcfirst, strtolower;
use function count, in_array, array_slice;


/**
 * Default value for incoming variables
 */
class IncomingDefaults
{
    public static string $languageCode = "en";
    public static int $optionsQuota = 20;
    public static ?int $searchType = null;
    public static int $allowedHotelCount = 20;

    public static int $allowedRoomCount = 1;
    public static int $allowedRoomGuestCount = 1;
    public static int $allowedChildCountPerRoom = 0;

    public static string $currency = "EUR";
    public static string $nationality = "US";
    public static array $markets = ["ES"];
    public static float $markup = 1;
}

/**
 * Incoming data preparing & validation & manipulation
 */
class IncomingData extends Castable
{
    protected ?string $varFiltersCg = null;
    protected ?string $optionsQuota = null;
    private ?IncomingParameters $parameters = null;
    private ?AuthInfo $auth = null;
    protected ?string $searchType = null;
    protected ?string $allowedHotelCount = null;
    protected ?string $allowedRoomCount = null;
    protected ?string $allowedRoomGuestCount = null;
    protected ?string $allowedChildCountPerRoom = null;
    protected ?array $availDestinations = null;
    protected ?string $startDate = null;
    protected ?string $endDate = null;
    protected ?string $currency = null;
    protected ?string $nationality = null;
    protected ?array $markets = null;
    // Array<RoomCandidate>
    protected ?array $roomCandidates = null;
    protected ?string $markup = null;

    private array $_propertiesToPass;

    protected function init(): void
    {
        $this->_propertiesToPass = [
            'LanguageCode' => "The 'languageCode' must be one of: en, fr, de, or es",
            'OptionsQuota' => "'optionsQuota' must be an integer no greater than 50",
            'Auth' => "'password', 'username' or 'CompanyID' is missing or incorrect",
            'SearchType' => "'SearchType' must be 'Single' or 'Multiple' ",
            'StartDate' => "'StartDate' must be at least 2 days after today",
            'EndDate' => "The stay duration ('EndDate' - 'StartDate') must be at least 3 nights",
            'Currency' => "'Currency' must be one of: EUR, USD, or GBP",
            'Nationality' => "'Nationality' must be one of: US, GB, or CA",
            'Markets' => "'Markets' must contain one or more of: US, GB, CA, or ES."
        ];
    }
    /**
     * Language Code Setter
     *
     * @param SimpleXMLElement|null $source
     * @return void
     */
    protected function setSource(?SimpleXMLElement $source): void
    {
        $this->varFiltersCg = $source->languageCode[0];
    }


    /**
     * Required Parameters Setter
     *
     * @param SimpleXMLElement|null $configuration
     * @return void
     */
    protected function setConfiguration(?SimpleXMLElement $configuration): void
    {
        $this->parameters = new IncomingParameters($configuration->Parameters);
        $this->auth = new AuthInfo($this->parameters);
    }

    /**
     * Available Destinations Setter
     *
     * @param SimpleXMLElement|null $availDestinations
     * @return void
     */
    protected function setAvailDestinations(?SimpleXMLElement $availDestinations): void
    {
        if (count($availDestinations)) {
            foreach ($availDestinations as $destination) {
                if ($destination->attributes()->code) {
                    $this->availDestinations[] = intval($destination->attributes()->code);
                }
            }
        }
    }

    /**
     * Markets Setter
     *
     * @param SimpleXMLElement|null $markets
     * @return void
     */
    protected function setMarkets(?SimpleXMLElement $markets): void
    {
        if (count($markets)) {
            foreach ($markets as $market) {
                $this->markets[] = strval($market);
            }
        }
    }

    /**
     * Rooms Setter
     *
     * @param SimpleXMLElement|null $roomCandidates
     * @return void
     */
    public function setRoomCandidates(?SimpleXMLElement $roomCandidates): void
    {
        if (is_object($roomCandidates)) {
            foreach ($roomCandidates as $room) {
                $this->roomCandidates[strval($room->attributes()->id)] = (new RoomCandidate($room))->getPaxes();
            }
        }
    }

    /**
     * Search Type
     *
     * Search Type should be in "Single" or "Multiple".
     * Maximum defined by AllowedHotelCount.
     *
     * @return string|null
     */
    public function getSearchType(): ?string
    {
        return is_null($this->searchType) ?
            IncomingDefaults::$searchType :
            (in_array(ucfirst(strtolower($this->searchType)), ["Single", "Multiple"]) ? $this->searchType : null);
    }

    /**
     * Max hotels count in request.
     *
     * Used only in Multiple SearchType
     *
     * @return int|null
     */
    public function getAllowedHotelCount(): ?int
    {
        return $this->getSearchType() == "Single" ?
            1 :
            ( is_null($this->allowedHotelCount) ?
                IncomingDefaults::$allowedHotelCount :
                intval($this->allowedHotelCount));
    }


    /**
     * Available Destinations
     *  - If SearchType is "Single", must contain exactly one element.
     *  - If SearchType is "Multiple", can have multiple elements
     *
     * @return array|null
     */
    public function getAvailDestinations(): ?array
    {
        return !is_null($this->availDestinations) && $this->getAllowedHotelCount() ?
            array_slice($this->availDestinations, 0, $this->getAllowedHotelCount()) :
            [];
    }

    public function getAllowedRoomCount(): ?int
    {
        return (is_numeric($this->allowedRoomCount) &&
            is_int($this->allowedRoomCount + 0) &&
            $this->allowedRoomCount > 0)
                ? intval($this->allowedRoomCount)
                : IncomingDefaults::$allowedRoomCount;
    }

    public function getAllowedRoomGuestCount(): ?int
    {
        return (is_numeric($this->allowedRoomGuestCount) &&
            is_int($this->allowedRoomGuestCount + 0) &&
            $this->allowedRoomGuestCount > 0)
                ? intval($this->allowedRoomGuestCount)
                : IncomingDefaults::$allowedRoomGuestCount;
    }

    public function getAllowedChildCountPerRoom(): ?int
    {
        return (is_numeric($this->allowedChildCountPerRoom) &&
            is_int($this->allowedChildCountPerRoom + 0) &&
            $this->allowedChildCountPerRoom >= 0)
                ? intval($this->allowedChildCountPerRoom)
                : IncomingDefaults::$allowedChildCountPerRoom;
    }




    /**
     * Language Code
     *
     * The languageCode must be one of: en, fr, de, or es.
     * If not provided, default to en.
     *
     * In some reasons I should use a variable named varFiltersCg instead of languageCode.
     *
     * @return string|null
     */
    public function getLanguageCode(): ?string
    {
        return is_null($this->varFiltersCg) ?
            IncomingDefaults::$languageCode :
            (in_array( strtolower($this->varFiltersCg), ["en", "fr", "de", "es"]) ? $this->varFiltersCg : null);
    }


    /**
     * StartDate must be at least 2 days after today.
     *
     * @return string|null
     */
    public function getStartDate(): ?string
    {
        return !is_null($this->startDate) &&
        preg_match("#\d{2}/\d{2}/\d{4}#", $this->startDate) &&
        DateTime::createFromFormat("d/m/Y", $this->startDate) >= new DateTime("now + 2 day") ?
                $this->startDate : null;
    }

    /**
     * The stay duration (EndDate - StartDate) must be at least 3 nights.
     *
     * @return string|null
     */
    public function getEndDate(): ?string
    {
        $startDate = $this->getStartDate();

        return !is_null($startDate) && !is_null($this->endDate) &&
        preg_match("#\d{2}/\d{2}/\d{4}#", $this->endDate) &&
        DateTime::createFromFormat("d/m/Y", $this->endDate) >=
        DateTime::createFromFormat("d/m/Y", $startDate)->add(DateInterval::createFromDateString('3 day')) ?
            $this->endDate : null;
    }

    /**
     * Options Quota
     *
     * optionsQuota must be an integer no greater than 50.
     * If not provided, default to 20.
     *
     * @return int|null
     */
    public function getOptionsQuota(): ?int
    {
        return is_null($this->optionsQuota) ?
            IncomingDefaults::$optionsQuota :
            ( is_numeric($this->optionsQuota) &&
                is_int($this->optionsQuota + 0) &&
                $this->optionsQuota > 0 &&
                $this->optionsQuota <= 50 ? intval($this->optionsQuota) : null );
    }

    public function getParameters(): IncomingParameters
    {
        return $this->parameters;
    }

    /**
     *
     *  Authentication Parameters
     *
     *  must include password, username, and CompanyID (integer)
     *
     *  Should raise an error if any are missing.
     *
     * @return AuthInfo
     */
    public function getAuth(): AuthInfo
    {
        return $this->auth;
    }

    /**
     * Currency
     *
     * Currency must be one of: EUR, USD, or GBP. Default to EUR.
     *
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return is_null($this->currency) ?
            IncomingDefaults::$currency :
            (in_array(strtoupper($this->currency) , ["EUR", "USD", "GBP"]) ? $this->currency : null);
    }

    /**
     * Nationality
     *
     * Nationality must be one of: US, GB, or CA. Default to US.
     *
     * @return string|null
     */
    public function getNationality(): ?string
    {
        return is_null($this->nationality) ?
            IncomingDefaults::$nationality :
            (in_array(strtoupper($this->nationality) , ["US", "GB", "CA"]) ? $this->nationality : null);
    }

    /**
     * Markets
     *
     * Markets must contain one or more of: US, GB, CA, or ES. Default to ES.
     *
     * @return array|string[]|null
     */
    public function getMarkets(): ?array
    {
        $markets = [];
        if (!is_null($this->markets)) {
            foreach ($this->markets as $market) {
                if (in_array(strtoupper($market) , ["US", "GB", "CA", "ES"])) {
                    $markets[] = $market;
                }
            }
        }

        return is_null($this->markets) ?
            IncomingDefaults::$markets :
            (count($markets) ? $markets : null);
    }

    /**
     * Room and Passenger Rules:
     * - Each Paxes block represents a room (maximum: AllowedRoomCount).
     * - Each Pax block represents a passenger (maximum per room: AllowedRoomGuestCount).
     * - Passengers with age 5 or under are "Child". Others are "Adult".
     * - A "Child" must have an accompanying "Adult" in the same room.
     * - Maximum "Child" per room: AllowedChildCountPerRoom.
     *
     * @return array
     */
    public function getRoomCandidates(): array
    {
        $rooms = [];
        if (!is_null($this->roomCandidates)) {
            foreach ($this->roomCandidates as $room) {
                if ( ($room["Adult"] + $room["Child"] <= $this->getAllowedRoomGuestCount()) &&
                    (
                        ($room["Adult"] && !$room["Child"]) ||
                        ($room["Adult"] && $room["Child"] && $room["Child"] <= $this->getAllowedChildCountPerRoom())
                    ) ) {
                    $rooms[] = $room;
                }
            }
            $rooms = array_slice($rooms, 0, $this->getAllowedRoomCount());
        }
        return $rooms;
    }

    public function getMarkup(): ?float
    {
        return is_null($this->markup) ? IncomingDefaults::$markup : (float) $this->markup;
    }

    public function checkProperties(bool $multiple = true): array
    {
        $errors = [];
        foreach ($this->_propertiesToPass as $propertyName => $propertyError) {
            if ( !($propertyName == "Auth"
                ? $this->getAuth()->isValid()
                : !is_null($this->{"get".$propertyName}()))) {
                $errors[] = $propertyError;
            }
        }
        return $errors;

    }


}

class IncomingParameters extends Castable
{
    public ?string $password = null;
    public ?string $username = null;
    public ?string $companyID = null;

    public function cast($object): void
    {
        if (is_object($object->Parameter)) {
            foreach ($object->Parameter as $parameter) {
                foreach ($parameter->attributes() as $key => $value) {
                    $key = lcfirst($key);
                    if (property_exists($this, $key)) {
                        $this->$key = $value;
                    }
                }
            }
        }
    }
}


class AuthInfo
{
    private ?string $password = null;
    private ?string $username = null;
    private ?string $companyID = null;

    public function __construct(?IncomingParameters $parameters)
    {
        foreach (["password", "username", "companyID"] as $property) {
            if (method_exists($this, "set".ucfirst($property))) {
                $this->{"set".ucfirst($property)}($parameters->$property);
            } elseif (property_exists($parameters, $property)) {
                $this->$property = $parameters->$property;
            }
        }
    }

    /**
     * Auth UserName
     *
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username && strlen($this->username) <= 64 ?
            $this->username :
            null;
    }

    /**
     * Auth User Password
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * Auth User CompanyID
     *
     * @return int|null
     */
    public function getCompanyID(): ?int
    {
        return is_numeric($this->companyID) &&
            is_int($this->companyID + 0) &&
            $this->companyID > 0 ?
                intval($this->companyID) :
                null;
    }

    /**
     * Auth validation
     *
     * must include password, username, and CompanyID (integer).
     *
     * Return FALSE if any are missing
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return !is_null($this->getPassword()) &&
            !is_null($this->getUsername()) &&
            !is_null($this->getCompanyID());
    }
}

class RoomCandidate extends Castable
{
    private ?array $paxes = ["Adult"=>0, "Child"=>0];

    protected function setPaxes(?SimpleXMLElement $paxes): void
    {
        foreach ($paxes as $pax) {
            if ( (new RoomPax($pax))->isChild() )
                $this->paxes["Child"]++;
            else
                $this->paxes["Adult"]++;
        }
    }

    public function getPaxes(): array
    {
        return $this->paxes;
    }
}

class RoomPax
{
    private $age = null;

    public function __construct(?SimpleXMLElement $pax)
    {
        $this->age = $pax->attributes()->age;
    }

    public function isChild(): bool
    {
        return intval($this->age) <= 5;
    }
}
