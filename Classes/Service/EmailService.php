<?php

namespace TYPO3\T3extblog\Service;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013-2016 Felix Nagel <info@felixnagel.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Handles email sending and templating.
 */
class EmailService implements SingletonInterface
{
    const TEMPLATE_FOLDER = 'Email';

    /**
     * Extension name.
     *
     * @var string
     */
    protected $extensionName = 't3extblog';

    /**
     * @var \TYPO3\CMS\Extbase\Object\ObjectManagerInterface
     * @inject
     */
    protected $objectManager;

    /**
     * Logging Service.
     *
     * @var \TYPO3\T3extblog\Service\LoggingService
     * @inject
     */
    protected $log;

    /**
     * @var \TYPO3\T3extblog\Service\SettingsService
     * @inject
     */
    protected $settingsService;

    /**
     * @var array
     */
    protected $settings;

    /**
     */
    public function initializeObject()
    {
        $this->settings = $this->settingsService->getTypoScriptSettings();
    }

    /**
     * This is the main-function for sending Mails.
     *
     * @param array  $mailTo
     * @param array  $mailFrom
     * @param string $subject
     * @param string $emailBody
     *
     * @return int the number of recipients who were accepted for delivery
     */
    public function send($mailTo, $mailFrom, $subject, $emailBody)
    {
        if (!($mailTo && is_array($mailTo) && GeneralUtility::validEmail(key($mailTo)))) {
            $this->log->error('Given mailto email address is invalid.', $mailTo);

            return false;
        }

        if (!($mailFrom && is_array($mailFrom) && GeneralUtility::validEmail(key($mailFrom)))) {
            $mailFrom = MailUtility::getSystemFrom();
        }

        /* @var $message \TYPO3\CMS\Core\Mail\MailMessage */
        $message = $this->objectManager->get('TYPO3\\CMS\\Core\\Mail\\MailMessage');
        $message
            ->setTo($mailTo)
            ->setFrom($mailFrom)
            ->setSubject($subject)
            ->setCharset(\TYPO3\T3extblog\Utility\GeneralUtility::getTsFe()->metaCharset);

        // Plain text only
        if (strip_tags($emailBody) == $emailBody) {
            $message->setBody($emailBody, 'text/plain');
        } else {
            // Send as HTML and plain text
            $message->setBody($emailBody, 'text/html');
            $message->addPart($this->preparePlainTextBody($emailBody), 'text/plain');
        }

        if (!$this->settings['debug']['disableEmailTransmission']) {
            $message->send();
        }

        $logData = array(
            'mailTo' => $mailTo,
            'mailFrom' => $mailFrom,
            'subject' => $subject,
            'emailBody' => $emailBody,
            'isSent' => $message->isSent(),
        );
        $this->log->dev('Email sent.', $logData);

        return $logData['isSent'];
    }

    /**
     * This functions renders template to use in Mails and Other views.
     *
     * @param array  $variables    Arguments for template
     * @param string $templatePath Choose a template
     *
     * @return string
     */
    public function render($variables, $templatePath = 'Default.txt')
    {
        if (version_compare(TYPO3_branch, '8.0', '>=')) {
            $emailView = $this->getEmailViewFor8x($templatePath);
        } else {
            // @todo Remove this when 7.x is no longer relevant
            $emailView = $this->getEmailViewFor7x($templatePath);
        }

        $emailView->assignMultiple($variables);
        $emailView->assignMultiple(array(
            'timestamp' => $GLOBALS['EXEC_TIME'],
            'domain' => GeneralUtility::getIndpEnv('TYPO3_SITE_URL'),
            'settings' => $this->settings,
        ));

        return $emailView->render();
    }

    /**
     * Create and configure the view.
     *
     * @param string $templateFile Choose a template
     *
     * @return StandaloneView
     */
    public function getEmailViewFor7x($templateFile)
    {
        $emailView = $this->createStandaloneView();

        $format = pathinfo($templateFile, PATHINFO_EXTENSION);
        $emailView->setFormat($format);

        $emailView->setTemplate(self::TEMPLATE_FOLDER.DIRECTORY_SEPARATOR.$templateFile);

        return $emailView;
    }

    /**
     * Create and configure the view.
     *
     * @param string $templateFile Choose a template
     *
     * @return StandaloneView
     */
    public function getEmailViewFor8x($templateFile)
    {
        $emailView = $this->createStandaloneView();

        $format = pathinfo($templateFile, PATHINFO_EXTENSION);
        $emailView->setFormat($format);
        $emailView->getTemplatePaths()->setFormat($format);

        $emailView->getRenderingContext()->setControllerName(self::TEMPLATE_FOLDER);
        $emailView->setTemplate($templateFile);

        return $emailView;
    }

    /**
     * @return StandaloneView
     */
    protected function createStandaloneView()
    {
        /* @var $emailView \TYPO3\CMS\Fluid\View\StandaloneView */
        $emailView = $this->objectManager->get('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
        $emailView->getRequest()->setPluginName('');
        $emailView->getRequest()->setControllerExtensionName($this->extensionName);

        $this->setViewPaths($emailView);

        return $emailView;
    }

    /**
     * @param \TYPO3\CMS\Fluid\View\StandaloneView $emailView
     */
    protected function setViewPaths($emailView)
    {
        $frameworkConfig = $this->settingsService->getFrameworkSettings();

        if (isset($frameworkConfig['view']['layoutRootPaths'])) {
            $emailView->setLayoutRootPaths($frameworkConfig['view']['layoutRootPaths']);
        }
        if (isset($frameworkConfig['view']['partialRootPaths'])) {
            $emailView->setPartialRootPaths($frameworkConfig['view']['partialRootPaths']);
        }
        if (isset($frameworkConfig['view']['templateRootPaths'])) {
            $emailView->setTemplateRootPaths($frameworkConfig['view']['templateRootPaths']);
        }
    }

    /**
     * Prepare html as plain text.
     *
     * @param string $html
     *
     * @return string
     */
    protected function preparePlainTextBody($html)
    {
        // Remove style tags
        $output = preg_replace('/<style\\b[^>]*>(.*?)<\\/style>/s', '', $html);

        // Remove tags and extract url from link tags
        $output = strip_tags(preg_replace('/<a.* href=(?:"|\')(.*)(?:"|\').*>/', '$1', $output));

        // Short URLs
        // @todo Create standalone or remove when 7.x is no longer relevant
        if (version_compare(TYPO3_branch, '8.0', '<')) {
            $output = GeneralUtility::substUrlsInPlainText($output);
        }

        // Break lines and clean up white spaces
        $output = MailUtility::breakLinesForEmail($output);
        $output = preg_replace('/(?:(?:\r\n|\r|\n)\s*){2}/s', "\n\n", $output);

        return $output;
    }
}
