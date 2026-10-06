<?php
/**
 * CubePath Cloud WHMCS Module - Session Helper
 *
 * Provides flash message functionality for displaying one-time success,
 * error, or info messages across page redirects.
 */

class SessionHelper
{
    /**
     * Store a flash message in the session.
     *
     * @param string $type    Message type ('success', 'error', 'info', 'warning')
     * @param string $message Message text
     */
    public static function setFlashMessage($type, $message)
    {
        $_SESSION['CUBEPATH']['FLASH'][] = array(
            'type'    => $type,
            'message' => $message,
        );
    }

    /**
     * Retrieve all flash messages and clear them from the session.
     *
     * @return array Array of flash message arrays with 'type' and 'message' keys
     */
    public static function getFlashMessages()
    {
        if (isset($_SESSION['CUBEPATH']['FLASH']))
        {
            $flash = $_SESSION['CUBEPATH']['FLASH'];
            unset($_SESSION['CUBEPATH']['FLASH']);
            return $flash;
        }

        return array();
    }
}
