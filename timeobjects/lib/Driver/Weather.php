<?php
/**
 * TimeObjects driver for exposing weatherunderground information via the
 * listTimeObjects API.
 *
 * Copyright 2011 Horde LLC (http://www.horde.org/)
 *
 * @author Michael J Rubinsky <mrubinsk@horde.org>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package TimeObjects
 */
class TimeObjects_Driver_Weather extends TimeObjects_Driver_Base
{
    protected $_units = Horde_Service_Weather::UNITS_STANDARD;
    protected $_forecastDays = Horde_Service_Weather::FORECAST_7DAY;
    protected $_driver;
    protected $_location;

    public function __construct(array $params)
    {
        global $registry, $prefs;

        $country = substr($GLOBALS['language'], -2);
        // Assume if it's passed in, we know it's valid.
        if (!empty($params['location'])) {
            $this->_location = $params['location'];
        } else {
            $this->_findLocation();
        }

        // Yup, we are the oddballs.
        if ($country != 'US') {
            $this->_units = Horde_Service_Weather::UNITS_METRIC;
        }

        parent::__construct($params);
    }

    /**
     * Ensures that we meet all requirements to use this time object
     *
     * @return boolean
     */
    public function ensure()
    {
        if (empty($this->_location)) {
            return false;
        }
        try {
            $this->_create();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     *
     * @param Horde_Date $start  The start time of the period
     * @param Horde_Date $end   The end time of the period
     *
     * @return array of listTimeObjects arrays.
     */
    public function listTimeObjects(Horde_Date $start = null, Horde_Date $end = null)
    {
        global $conf, $prefs;

        // No need to continue if the forecast days are not in the current
        // range.
        $forecast_start = new Horde_Date(time());
        $forecast_end = clone $forecast_start;
        $forecast_end->mday += 7;
        // Today is day 1, so subtract a day
        if ($end->before($forecast_start) || $start->after($forecast_end)) {
            return array();
        }

        $weather = $this->_create();
        $units = $weather->getUnits($weather->units);
        $forecast = $weather->getForecast($this->_location, Horde_Service_Weather::FORECAST_7DAY);
        $current = $weather->getCurrentConditions($this->_location);

        $objects = array();
        foreach ($forecast as $data) {
            $day = $data->date;
            $day->hour = 0;
            $day->min = 0;
            $day->sec = 0;
            $day_end = clone $day;
            $day_end->mday++;

            $title = sprintf(
                '%s %d°%s/%d°%s',
                $data->conditions,
                $data->high,
                $units['temp'],
                $data->low,
                $units['temp']
            );

            $description = sprintf(
                _("%s\nHigh temperature: %d%s\nPrecipitation: %d%%"),
                _($data->conditions),
                $data->high,
                '°' . $units['temp'],
                $data->precipitation_percent
            );
            $station = $weather->getStation();

            $description = sprintf(
                _("Location: %s\nSunrise: %s\nSunset: %s\n\nConditions\n%s"),
                $weather->getStation()->name,
                $weather->getStation()->sunrise,
                $weather->getStation()->sunset,
                $description);

            $objects[] = array(
                'id' => $day->timestamp(), //???
                'title' => $title,
                'description' => $description,
                'start' => $day->strftime('%Y-%m-%dT00:00:00'),
                'end' => $day_end->strftime('%Y-%m-%dT00:00:00'),
                'recurrence' => Horde_Date_Recurrence::RECUR_NONE,
                'params' => array(),
                'link' => new Horde_Url('#'),
                'icon' => (string)Horde_Themes::img('weather/23x23/' . $data->icon)
            );

            $day->mday++;
        }

        return $objects;
    }

    protected function _findLocation()
    {
        global $registry, $injector;

        // First use the location pref, then turba's "own" contact, followed
        // general IP location?
        $identity = $injector
            ->getInstance('Horde_Core_Factory_Identity')
            ->create();
        if (!($location = $identity->getValue('location')) &&
            $registry->hasInterface('contacts')) {
            try {
                $contact = $GLOBALS['registry']->contacts->ownContact();
            } catch (Exception $e) {
            }
            if (!empty($contact['homeCountry'])) {
                $country = $contact['homeCountry'];
            } elseif (!empty($contact['workCountry'])) {
                $country = $contact['workCountry'];
            }
            if (!empty($contact['homeCity'])) {
                $location = $contact['homeCity']
                    . (!empty($contact['homeProvince']) ? ', ' . $contact['homeProvince'] : '')
                    . (!empty($contact['homeCountry']) ? ', ' . $contact['homeCountry'] : '');
            } else {
                $location = $contact['workCity']
                    . (!empty($contact['workProvince']) ? ', ' . $contact['workProvince'] : '')
                    . (!empty($contact['workCountry']) ? ', ' . $contact['workCountry'] : '');
            }
        }

        // Ensure we have a valid location code for the location.
        try {
            $driver = $this->_create();
        } catch (Exception $e) {
            return;
        }
        if (!empty($location)) {
            try {
                $location = $driver->searchLocations($location);
            } catch (Horde_Service_Weather_Exception $e) {
                Horde::logMessage($e, 'ERR');
                throw new Timeobjects_Exception($e);
            }
        } else {
            try {
                $location = $driver->searchLocations(
                    $GLOBALS['browser']->getIPAddress(),
                    Horde_Service_Weather::SEARCHTYPE_IP);
            } catch (Horde_Service_Weather_Exception $e) {
                return;
            }
        }
        $this->_location = $location->code;
    }

    /**
     * Private factory for weather driver.
     *
     * @return Horde_Service_Weather_Base
     */
    protected function _create()
    {
        try {
            $driver = $GLOBALS['injector']->getInstance('Horde_Weather');
        } catch (Exception $e) {
            throw new Timeobjects_Exception($e);
        }
        // Suggest units, but the driver may override this (like Google).
        $driver->units = $this->_units;

        return $driver;
    }

}