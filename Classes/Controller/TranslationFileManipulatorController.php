<?php
namespace PITS\TranslationHelper\Controller;

/*
 * This file is part of the PITS.TranslationHelper package.
 */

use Neos\Flow\Annotations as Flow;

class TranslationFileManipulatorController extends \Neos\Flow\Mvc\Controller\ActionController
{
    /**
     * @var string
     */
    protected $defaultViewObjectName = 'Neos\Flow\Mvc\View\JsonView';

    /**
     * @Flow\Inject
     * @var \PITS\TranslationHelper\Domain\Service\CommonSevices
     */
    protected $commonSevices;

    /**
     * @Flow\Inject
     * @var \PITS\TranslationHelper\Domain\Session\TranslationManagement
     */
    protected $session;

    /**
     * @Flow\Inject
     * @var \Neos\Flow\Security\Context
     */
    protected $securityContext;

    /**
     * This function receives the Ajax request and process this request.
     * Based on the request, it performs the add, delete, and update operations on the translation file.
     *
     * @param string $id Translation Id
     * @param string $label Translation Label
     * @param string $language TranslationLanguage
     * @param int $cdataChecker Translation CDATA Content Checker
     * @param int $encodingChecker Translation Unit Encoding Decision Checker
     *
     * @return void
     */
    public function indexAction($id = "", $label = "", $language = '', $cdataChecker = 0, $encodingChecker = 0)
    {
        $isValid = $this->commonSevices->validateTranslationLabel($label, $cdataChecker, $language, $encodingChecker);
        $filePath      = $this->commonSevices->getTranslationFileFullPath($language);
        
        if ($isValid) {
            $output = [
                "status"  => "error",
                "message" => $isValid,
            ];
        } else {
            $output = $this->commonSevices->performOpertions($filePath, $id, $label, $cdataChecker, $encodingChecker);
        }

        $this->view->assign('value', $output);
    }
        
    /**
     * This function receives the Ajax request and process this request.
     * Based on the request, it removes the requested translation unit from the translation file.
     *
     * @param string $translationId
     * @param string $csrfToken
     *
     * @return void
     */
    public function deleteTransaltionUnitAction(
        $translationId = "",
        $csrfToken = ""
    ) {
        $output = array(
            "status"  => "error",
            "message" => "Invalid Inputs.",
        );
        $flag     = 0;
        $errorMsg = array();
        try {
            $currentCsrfToken = $this->securityContext->getCsrfProtectionToken();
            if (trim($csrfToken) != trim($currentCsrfToken)) {
                $flag1 = 1;
            }

            if ($flag == 1) {
                $output = array(
                    "status"  => "error",
                    "message" => implode(",", $errorMsg),
                );
            } else {
                $availableSiteLanguages   = $this->commonSevices->getCurrentActiveSiteLanguages();
                $packageKey               = $this->session->getTranslationPackageKey();
                $translationsResourcePath = $this->commonSevices->getFlowPackageResourceTranslationPath($packageKey);
                $translationFile          = $this->session->getTranslationFile();

                if (empty($availableSiteLanguages) == false) {
                    $transUnitRemovalFlag = 0;
                    foreach ($availableSiteLanguages as $translationLanguage) {
                        $translationFileFullPath = trim($translationsResourcePath) . trim($translationLanguage) . "/" . trim($translationFile);
                        if ((is_file($translationFileFullPath) == true) && (file_exists($translationFileFullPath) == true)) {
                            $transUnitRemovalResult = $this->commonSevices->removeTranslationUnitFromCurrentTranslationFile($translationFileFullPath, $translationId);
                            if ($transUnitRemovalResult == false) {
                                $transUnitRemovalFlag = 1;
                            }
                        }
                    }

                    if ($transUnitRemovalFlag == 0) {
                        $output = array(
                            "status"  => "success",
                            "message" => $this->commonSevices->getDataSavedSuccessfullyMsg("en"),
                        );
                    } else {
                        $output = array(
                            "status"  => "error",
                            "message" => "Some problem in translation unit removal process",
                        );
                    }
                }
            }
        } catch (\Exception $e) {
            $output = array(
                "status"  => "error",
                "message" => $e->getMessage(),
            );
        }
        $this->view->assign('value', $output);
    }

    /**
     * This function receives the Ajax request and process this request.
     * Based on the request, it adds translation unit to the translation file.
     *
     * @param string $translationId
     * @param string $csrfToken
     * @param array $translationUnitLabels
     * @param array $translationUnitLanguages
     * @param array $translationUnitCDATASections
     * @param array $translationUnitEncodingDecisions
     *
     * @return void
     */
    public function addTransaltionUnitAction(
        $translationId = "",
        $csrfToken = "",
        $translationUnitLabels = array(),
        $translationUnitLanguages = array(),
        $translationUnitCDATASections = array(),
        $translationUnitEncodingDecisions = array()
    ) {
        $output = array(
            "status"  => "error",
            "message" => "Invalid Inputs.",
        );
        $flag     = 0;
        $errorMsg = array();
        try {
            $packageKey               = $this->session->getTranslationPackageKey();
            $translationsResourcePath = $this->commonSevices->getFlowPackageResourceTranslationPath($packageKey);
            $translationFile          = $this->session->getTranslationFile();
            $duplicateTranslationId   = 0;

            if (empty($translationUnitLanguages) == true) {
                $errorMsg[] = "Empty translation language input.";
                $flag       = 1;
            } else {
                $translationUnitValidityCheckRegExp = "/^[a-zA-Z]([a-zA-Z0-9_\.]*)[a-zA-Z0-9]$/i";
                if (preg_match($translationUnitValidityCheckRegExp, $translationId) != 1) {
                    $errorMsg[] = "Incorrect translation unit Id.";
                    $flag       = 1;
                } else {
                    foreach ($translationUnitLanguages as $translationUnitLanguage) {
                        $translationFileFullPath = trim($translationsResourcePath) . trim($translationUnitLanguage) . "/" . trim($translationFile);
                        if ((is_file($translationFileFullPath) == true) && (file_exists($translationFileFullPath) == true)) {
                            $duplicateTranslationUnit = $this->commonSevices->checkGivenTranslationIdExists($translationFileFullPath, $translationId);
                            if (empty($duplicateTranslationUnit) == false) {
                                $duplicateTranslationId = 1;
                            }
                        }
                    }
                    if ($duplicateTranslationId == 1) {
                        $errorMsg[] = "Duplicate translation unit.";
                        $flag       = 1;
                    } else {
                        foreach ($translationUnitLanguages as $translationUnitLanguagekey => $translationUnitLanguage) {
                            $translationFileFullPath = trim($translationsResourcePath) . trim($translationUnitLanguage) . "/" . trim($translationFile);
                            if ((is_file($translationFileFullPath) == true) && (file_exists($translationFileFullPath) == true)) {
                                $translationLabel               = "";
                                $translationCDATAContentChecker = 0;
                                $translationUnitEncodingDecisionChecker = 0;
                                if (isset($translationUnitLabels[$translationUnitLanguagekey]) == true) {
                                    $translationLabel = $translationUnitLabels[$translationUnitLanguagekey];
                                }
                                if (isset($translationUnitCDATASections[$translationUnitLanguagekey]) == true) {
                                    $translationCDATAContentChecker = $translationUnitCDATASections[$translationUnitLanguagekey];
                                }
                                if (isset($translationUnitEncodingDecisions[$translationUnitLanguagekey]) == true) {
                                    $translationUnitEncodingDecisionChecker = $translationUnitEncodingDecisions[$translationUnitLanguagekey];
                                }
                                $translationUnitCommonValidationResult = $this->commonSevices->validateTranslationLabel($translationLabel, $translationCDATAContentChecker, $translationUnitLanguage, $translationUnitEncodingDecisionChecker);
                                if (empty($translationUnitCommonValidationResult) == false) {
                                    $errorMsg[] = trim($translationUnitCommonValidationResult);
                                    $flag       = 1;
                                }
                            }
                        }
                    }
                }
            }
            if ($flag == 1) {
                $output = array(
                    "status"  => "error",
                    "message" => implode(",", $errorMsg),
                );
            } else {
                $translationUnitAdditionSuccess = true;
                foreach ($translationUnitLanguages as $translationUnitLanguagekey => $translationUnitLanguage) {
                    $translationFileFullPath = trim($translationsResourcePath) . trim($translationUnitLanguage) . "/" . trim($translationFile);
                    if ((is_file($translationFileFullPath) == true) && (file_exists($translationFileFullPath) == true)) {
                        $translationLabel               = "";
                        $translationCDATAContentChecker = 0;
                        if (isset($translationUnitLabels[$translationUnitLanguagekey]) == true) {
                            $translationLabel = $translationUnitLabels[$translationUnitLanguagekey];
                        }
                        if (isset($translationUnitCDATASections[$translationUnitLanguagekey]) == true) {
                            $translationCDATAContentChecker = $translationUnitCDATASections[$translationUnitLanguagekey];
                        }
                        if (isset($translationUnitEncodingDecisions[$translationUnitLanguagekey]) == true) {
                            $translationUnitEncodingDecisionChecker = $translationUnitEncodingDecisions[$translationUnitLanguagekey];
                        }
                        $translationUnitAdditionSuccess = $this->commonSevices->addNewTranslationUnitToCurrentTranslationFile($translationFileFullPath, $translationId, $translationLabel, $translationCDATAContentChecker, $translationUnitEncodingDecisionChecker);
                    }
                }

                if ($translationUnitAdditionSuccess == true) {
                    $output = array(
                        "status"  => "success",
                        "message" => $this->commonSevices->getDataSavedSuccessfullyMsg("en"),
                    );
                } else {
                    $output = array(
                        "status"  => "error",
                        "message" => "Some problem in translation unit addition process",
                    );
                }
            }
        } catch (\Exception $e) {
            $output = array(
                "status"  => "error",
                "message" => $e->getMessage(),
            );
        }
        $this->view->assign('value', $output);
    }
}
