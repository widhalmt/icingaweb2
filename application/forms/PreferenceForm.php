<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Forms;

use Exception;
use DateTimeZone;
use Icinga\Application\Logger;
use Icinga\Authentication\Manager;
use Icinga\User\Preferences;
use Icinga\User\Preferences\PreferencesStore;
use Icinga\Util\TimezoneDetect;
use Icinga\Util\Translator;
use Icinga\Web\Form;
use Icinga\Web\Notification;
use Icinga\Web\Session;

/**
 * Form class to adjust user preferences
 */
class PreferenceForm extends Form
{
    /**
     * The preferences to work with
     *
     * @var Preferences
     */
    protected $preferences;

    /**
     * The preference store to use
     *
     * @var PreferencesStore
     */
    protected $store;

    /**
     * Initialize this form
     */
    public function init()
    {
        $this->setName('form_config_preferences');
    }

    /**
     * Set preferences to work with
     *
     * @param   Preferences     $preferences    The preferences to work with
     *
     * @return  self
     */
    public function setPreferences(Preferences $preferences)
    {
        $this->preferences = $preferences;
        return $this;
    }

    /**
     * Set the preference store to use
     *
     * @param   PreferencesStore    $store      The preference store to use
     *
     * @return  self
     */
    public function setStore(PreferencesStore $store)
    {
        $this->store = $store;
    }

    /**
     * Persist preferences
     *
     * @return  self
     */
    public function save()
    {
        $this->store->save($this->preferences);
        return $this;
    }

    /**
     * Adjust preferences and persist them
     *
     * @see Form::onSuccess()
     */
    public function onSuccess()
    {
        $this->preferences = new Preferences($this->store->load());

        $webPreferences = $this->preferences->get('icingaweb', array());
        foreach ($this->getValues() as $key => $value) {
            if ($value === null || $value === 'autodetect') {
                if (isset($webPreferences[$key])) {
                    unset($webPreferences[$key]);
                }
            } else {
                $webPreferences[$key] = $value;
            }
        }
        $this->preferences->icingaweb = $webPreferences;

        Session::getSession()->user->setPreferences($this->preferences);

        try {
            if ($this->getElement('btn_submit_preferences')->isChecked()) {
                $this->save();
                Notification::success(t('Preferences successfully saved'));
            } else {
                Notification::success(t('Preferences successfully saved for the current session'));
            }
        } catch (Exception $e) {
            Logger::error($e);
            Notification::error($e->getMessage());
        }
    }

    /**
     * Populate preferences
     *
     * @see Form::onRequest()
     */
    public function onRequest()
    {
        $auth = Manager::getInstance();
        $values = $auth->getUser()->getPreferences()->get('icingaweb');

        if (! isset($values['language'])) {
            $values['language'] = 'autodetect';
        }

        if (! isset($values['timezone'])) {
            $values['timezone'] = 'autodetect';
        }

        $this->populate($values);
    }

    /**
     * @see Form::createElements()
     */
    public function createElements(array $formData)
    {
        $languages = array();
        $languages['autodetect'] = sprintf(t('Browser (%s)', 'preferences.form'), $this->getLocale());
        foreach (Translator::getAvailableLocaleCodes() as $language) {
            $languages[$language] = $language;
        }

        $tzList = array();
        $tzList['autodetect'] = sprintf(t('Browser (%s)', 'preferences.form'), $this->getDefaultTimezone());
        foreach (DateTimeZone::listIdentifiers() as $tz) {
            $tzList[$tz] = $tz;
        }

        $this->addElement(
            'select',
            'language',
            array(
                'required'      => true,
                'label'         => t('Your Current Language'),
                'description'   => t('Use the following language to display texts and messages'),
                'multiOptions'  => $languages,
                'value'         => substr(setlocale(LC_ALL, 0), 0, 5)
            )
        );

        $this->addElement(
            'select',
            'timezone',
            array(
                'required'      => true,
                'label'         => t('Your Current Timezone'),
                'description'   => t('Use the following timezone for dates and times'),
                'multiOptions'  => $tzList,
                'value'         => $this->getDefaultTimezone()
            )
        );

        $this->addElement(
            'checkbox',
            'show_benchmark',
            array(
                'required'  => true,
                'label'     => t('Use benchmark')
            )
        );

        $this->addElement(
            'submit',
            'btn_submit_preferences',
            array(
                'ignore'        => true,
                'label'         => t('Save to the Preferences'),
                'decorators'    => array(
                    'ViewHelper',
                    array('HtmlTag', array('tag' => 'div'))
                )
            )
        );

        $this->addElement(
            'submit',
            'btn_submit_session',
            array(
                'ignore'        => true,
                'label'         => t('Save for the current Session'),
                'decorators'    => array(
                    'ViewHelper',
                    array('HtmlTag', array('tag' => 'div'))
                )
            )
        );

        $this->addDisplayGroup(
            array('btn_submit_preferences', 'btn_submit_session'),
            'submit_buttons',
            array(
                'decorators' => array(
                    'FormElements',
                    array('HtmlTag', array('tag' => 'div', 'class' => 'control-group'))
                )
            )
        );
    }

    /**
     * Return the current default timezone
     *
     * @return  string
     */
    protected function getDefaultTimezone()
    {
        $detect = new TimezoneDetect();
        if ($detect->success()) {
            return $detect->getTimezoneName();
        } else {
            return @date_default_timezone_get();
        }
    }

    /**
     * Return the preferred locale based on the given HTTP header and the available translations
     *
     * @return string
     */
    protected function getLocale()
    {
        $locale = Translator::getPreferredLocaleCode($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        return $locale;
    }
}
