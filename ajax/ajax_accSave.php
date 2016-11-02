<?php
/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2015 Rubén Domínguez nuxsmin@syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

use SP\Account\Account;
use SP\Core\ActionsInterface;
use SP\Core\Crypt;
use SP\Core\Init;
use SP\Core\Session;
use SP\Core\SessionUtil;
use SP\Core\Exceptions\SPException;
use SP\DataModel\AccountExtData;
use SP\DataModel\CustomerData;
use SP\DataModel\CustomFieldData;
use SP\Http\Request;
use SP\Http\Response;
use SP\Mgmt\Customers\Customer;
use SP\Mgmt\CustomFields\CustomField;
use SP\Mgmt\CustomFields\CustomFieldsUtil;

define('APP_ROOT', '..');

require_once APP_ROOT . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'Base.php';

Request::checkReferer('POST');

if (!Init::isLoggedIn()) {
    Response::printJson(_('La sesión no se ha iniciado o ha caducado'), 10);
}

$sk = Request::analyze('sk', false);

if (!$sk || !SessionUtil::checkSessionKey($sk)) {
    Response::printJson(_('CONSULTA INVÁLIDA'));
}

// Variables POST del formulario
$actionId = Request::analyze('actionId', 0);
$accountId = Request::analyze('accountid', 0);
$customerId = Request::analyze('customerId', 0);
$newCustomer = Request::analyze('customer_new');
$accountName = Request::analyze('name');
$accountLogin = Request::analyze('login');
$accountPassword = Request::analyzeEncrypted('pass');
$accountPasswordR = Request::analyzeEncrypted('passR');
$categoryId = Request::analyze('categoryId', 0);
$accountOtherGroups = Request::analyze('othergroups', 0);
$accountOtherUsers = Request::analyze('otherusers', 0);
$accountNotes = Request::analyze('notes');
$accountUrl = Request::analyze('url');
$accountGroupEditEnabled = Request::analyze('geditenabled', 0, false, 1);
$accountUserEditEnabled = Request::analyze('ueditenabled', 0, false, 1);
$accountMainGroupId = Request::analyze('mainGroupId', 0);
$customFieldsHash = Request::analyze('hashcf');
$customFields = Request::analyze('customfield');
$tags = Request::analyze('tags');

// Datos del Usuario
$currentUserId = Session::getUserId();

if ($accountMainGroupId === 0) {
    $accountMainGroupId = Session::getUserGroupId();
}

if ($actionId === ActionsInterface::ACTION_ACC_NEW
    || $actionId === ActionsInterface::ACTION_ACC_COPY
) {
    // Comprobaciones para nueva cuenta
    if (!$accountName) {
        Response::printJson(_('Es necesario un nombre de cuenta'));
    } elseif (!$customerId && !$newCustomer) {
        Response::printJson(_('Es necesario un nombre de cliente'));
    } elseif (!$accountLogin) {
        Response::printJson(_('Es necesario un usuario'));
    } elseif (!$accountPassword || !$accountPasswordR) {
        Response::printJson(_('Es necesaria una clave'));
    } elseif (!$categoryId) {
        Response::printJson(_('Es necesario una categoría'));
    }
} elseif ($actionId === ActionsInterface::ACTION_ACC_EDIT) {
    // Comprobaciones para modificación de cuenta
    if (!$customerId && !$newCustomer) {
        Response::printJson(_('Es necesario un nombre de cliente'));
    } elseif (!$accountName) {
        Response::printJson(_('Es necesario un nombre de cuenta'));
    } elseif (!$accountLogin) {
        Response::printJson(_('Es necesario un usuario'));
    } elseif (!$categoryId) {
        Response::printJson(_('Es necesario una categoría'));
    }
} elseif ($actionId === ActionsInterface::ACTION_ACC_DELETE) {
    if (!$accountId) {
        Response::printJson(_('Id inválido'));
    }
} elseif ($actionId === ActionsInterface::ACTION_ACC_EDIT_PASS) {
    // Comprobaciones para modficación de clave
    if (!$accountPassword || !$accountPasswordR) {
        Response::printJson(_('Es necesaria una clave'));
    }
} elseif ($actionId === ActionsInterface::ACTION_ACC_EDIT_RESTORE) {
    if (!$accountId) {
        Response::printJson(_('Id inválido'));
    }
} else {
    Response::printJson(_('Acción Inválida'));
}

if ($actionId === ActionsInterface::ACTION_ACC_NEW
    || $actionId === ActionsInterface::ACTION_ACC_COPY
    || $actionId === ActionsInterface::ACTION_ACC_EDIT_PASS
) {
    if ($accountPassword !== $accountPasswordR) {
        Response::printJson(_('Las claves no coinciden'));
    }

    try {
        // Encriptar clave de cuenta
        $accountEncPass = Crypt::encryptData($accountPassword);
    } catch (SPException $e) {
        Response::printJson($e->getMessage());
    }
}

$AccountData = new AccountExtData();
$AccountData->setAccountId($accountId);
$AccountData->setAccountName($accountName);
$AccountData->setAccountCustomerId($customerId);
$AccountData->setAccountCategoryId($categoryId);
$AccountData->setAccountLogin($accountLogin);
$AccountData->setAccountUrl($accountUrl);
$AccountData->setAccountNotes($accountNotes);
$AccountData->setAccountUserEditId($currentUserId);
$AccountData->setAccountOtherUserEdit($accountUserEditEnabled);
$AccountData->setAccountOtherGroupEdit($accountGroupEditEnabled);

if (is_array($accountOtherUsers)) {
    $AccountData->setUsersId($accountOtherUsers);
}

if (is_array($accountOtherGroups)) {
    $AccountData->setUserGroupsId($accountOtherGroups);
}

if (is_array($tags)) {
    $AccountData->setTags($tags);
}

$Account = new Account($AccountData);

$CustomFieldData = new CustomFieldData();
$CustomFieldData->setId($accountId);
$CustomFieldData->setModule(ActionsInterface::ACTION_ACC);

switch ($actionId) {
    case ActionsInterface::ACTION_ACC_NEW:
    case ActionsInterface::ACTION_ACC_COPY:
        $AccountData->setAccountPass($accountEncPass['data']);
        $AccountData->setAccountIV($accountEncPass['iv']);
        $AccountData->setAccountUserId($currentUserId);
        $AccountData->setAccountUserGroupId($accountMainGroupId);

        // Crear cuenta
        if ($Account->createAccount()) {
            if (is_array($customFields)) {
                $CustomFieldData->setId($AccountData->getAccountId());
                CustomFieldsUtil::addItemCustomFields($customFields, $CustomFieldData);
            }

            Response::printJson(_('Cuenta creada'), 0);
        }

        Response::printJson(_('Error al crear la cuenta'), 0);
        break;
    case ActionsInterface::ACTION_ACC_EDIT:
        // Cambiar el grupo principal si el usuario es Admin
        if (Session::getUserIsAdminApp() || Session::getUserIsAdminAcc()) {
            $AccountData->setAccountUserGroupId($accountMainGroupId);
        }

        // Actualizar cuenta
        if ($Account->updateAccount()) {
            if (is_array($customFields)) {
                CustomFieldsUtil::updateItemCustomFields($customFields, $CustomFieldData);
            }

            Response::printJson(_('Cuenta actualizada'), 0);
        }

        Response::printJson(_('Error al modificar la cuenta'));
        break;
    case ActionsInterface::ACTION_ACC_DELETE:
        // Eliminar cuenta
        if ($Account->deleteAccount()
            && CustomField::getItem($CustomFieldData)->delete($accountId)
        ) {
            Response::printJson(_('Cuenta eliminada'), 0);
        }

        Response::printJson(_('Error al eliminar la cuenta'));
        break;
    case ActionsInterface::ACTION_ACC_EDIT_PASS:
        $AccountData->setAccountPass($accountEncPass['data']);
        $AccountData->setAccountIV($accountEncPass['iv']);

        // Actualizar clave de cuenta
        if ($Account->updateAccountPass()) {
            Response::printJson(_('Clave actualizada'), 0);
        }

        Response::printJson(_('Error al actualizar la clave'));
        break;
    case ActionsInterface::ACTION_ACC_EDIT_RESTORE:
        $AccountData->setAccountId(\SP\Account\AccountHistory::getAccountIdFromId($accountId));
        $AccountData->setAccountUserEditId($currentUserId);

        if ($Account->restoreFromHistory($accountId)) {
            Response::printJson(_('Cuenta restaurada'), 0);
        }

        Response::printJson(_('Error al restaurar cuenta'));

        break;
    default:
        Response::printJson(_('Acción Inválida'));
}