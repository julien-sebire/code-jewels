<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 */

/**
 * UserController class
 *
 * @package common_core
 */
class common_core_UserController extends \common_core_BaseController
{

    /**
     * Entrypoint of user creation
     *
     */
    public function create()
    {

        try {
            $params = common_http_Request::currentRequest()->getParams();

            if($params['name'] == '' || $params['email'] == '' || $params['password'] == '' || $params['repeat_password'] == '')
            {
                common_Logger::e('Missing field "name", "email", "password" or "repeat_password"');
                return $this->errorResponse('Missing field', 400);
            } elseif($params['password'] !== $params['repeat_password']) {
                common_Logger::e('"password" and "repeat_password" has to be identical');
                return $this->errorResponse('"password" and "repeat_password" has to be identical', 400);
            } else {
                $persistenceType = $this->getServiceLocator()->getConfig('persistenceType');
                if ($persistenceType == 'kvstore') {
                    $persistence = $this->getServiceLocator()->get(\common_persistence_KeyValueStore::SERVICE_ID);
                    $retrieveByEmailMethod = 'findUserByEmail';
                } else {
                    $persistence = $this->getServiceLocator()->get(\common_persistence_RDSStore::SERVICE_ID); // persistence is RDS
                    $retrieveByEmailMethod = 'getUserByEmail';
                }
                if ($persistence->$retrieveByEmailMethod($params['email']) instanceof common_user_UserEntity) {
                        $message = "{$params['email']} already registered";
                        common_Logger::e($message);
                        return $this->errorResponse($message, 400);
                }
            }

            if ($persistenceType == 'kvstore') {
                try {
                    $user = new User;
                    $user->setName($params['name']);
                    $user->setEmail($params['email']);
                    $user->setPassword($params['password']);
                    $persistence->createNewKey('user', $user);
                } catch(\common_persistence_KeyValueStore_Exception $e) {
                    common_Logger::e('there has been a problem registering the user');
                    return $this->errorResponse('there has been a problem registering the user', 400);
                }

            } else {
                try {
                $sql = 'INSERT INTO user_user (name, mail, pass)' . ' VALUES ("' . $params['name'] . '", "' . $params['email'] . '", "' . $params['password'] . '")';
                $persistence->execute($sql);
                } catch(\common_persistence_RDSStore_Exception $e) {
                    common_Logger::e('there has been a problem registering the user');
                    return $this->errorResponse('there has been a problem registering the user', 400);
                }

            }

            $success = sprintf('user "%s" has been registered', $params['name']);
            common_Logger::i($success);
            return $this->response($success, 200);

        } catch (common_service_ServiceNotFoundException $e) {
            common_Logger::e($e->getMessage());
            return $this->errorResponse($message, 500);
        } catch (common_service_ServiceNotConfiguredException $e) {
            common_Logger::e($e->getMessage());

            if (common_helpers_Request::isAjax()) {
                throw new common_exception_IsAjaxAction(__CLASS__ . '::' . __FUNCTION__);
            } else
            return $this->errorResponse($message, 500);
        } catch (\Exception $e) {
            common_Logger::e($e->getMessage());
            return $this->errorResponse($message, 500);
        }
    }


}
