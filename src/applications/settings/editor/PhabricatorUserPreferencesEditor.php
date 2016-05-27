<?php

final class PhabricatorUserPreferencesEditor
  extends AlmanacEditor {

  public function getEditorObjectsDescription() {
    return pht('Settings');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorUserPreferencesTransaction::TYPE_SETTING;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        return $object->getPreference($setting_key);
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $actor = $this->getActor();

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    $settings = PhabricatorSetting::getAllEnabledSettings($actor);
    $setting = $settings[$setting_key];

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        $value = $xaction->getNewValue();
        $value = $setting->getTransactionNewValue($value);
        return $value;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        $new_value = $xaction->getNewValue();
        if ($new_value === null) {
          $object->unsetPreference($setting_key);
        } else {
          $object->setPreference($setting_key, $new_value);
        }
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    $actor = $this->getActor();
    $settings = PhabricatorSetting::getAllEnabledSettings($actor);

    switch ($type) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        foreach ($xactions as $xaction) {
          $setting_key = $xaction->getMetadataValue(
            PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

          $setting = idx($settings, $setting_key);
          if (!$setting) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'There is no known application setting with key "%s".',
                $setting_key),
              $xaction);
            continue;
          }

          try {
            $setting->validateTransactionValue($xaction->getNewValue());
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
          }
        }
        break;
    }

    return $errors;
  }

}
