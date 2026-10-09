<?php

namespace CubePath\WHMCS\Addon;

use WHMCS\Database\Capsule;

/**
 * Product welcome email sent by WHMCS once a VPS is created. Like other
 * provisioning modules, the addon only adds the template: WHMCS sends it
 * through the mail provider set in System Settings > Mail.
 */
class WelcomeEmail
{
    const NAME = 'CubePath VPS Welcome Email';

    /**
     * Add the template (and its Spanish translation) unless it exists, so
     * edits made by the admin are kept.
     *
     * @return int Template ID
     */
    public static function ensure()
    {
        foreach (self::templates() as $language => $template)
        {
            $exists = Capsule::table('tblemailtemplates')
                ->where('type', 'product')
                ->where('name', self::NAME)
                ->where('language', $language)
                ->exists();
            if ($exists)
            {
                continue;
            }

            Capsule::table('tblemailtemplates')->insert(array(
                'type'          => 'product',
                'name'          => self::NAME,
                'subject'       => $template['subject'],
                'message'       => $template['message'],
                'attachments'   => '',
                'fromname'      => '',
                'fromemail'     => '',
                'disabled'      => 0,
                'custom'        => 1,
                'language'      => $language,
                'copyto'        => '',
                'blind_copy_to' => '',
                'plaintext'     => 0,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ));
        }

        return self::id();
    }

    /**
     * ID of the default-language template, or 0 when it is missing.
     */
    public static function id()
    {
        return (int)Capsule::table('tblemailtemplates')
            ->where('type', 'product')
            ->where('name', self::NAME)
            ->where('language', '')
            ->value('id');
    }

    /**
     * Use the template as the welcome email of a product that has none.
     */
    public static function assign($productId)
    {
        $templateId = self::ensure();
        if (!$templateId)
        {
            return;
        }

        Capsule::table('tblproducts')
            ->where('id', (int)$productId)
            ->where('welcomeemail', 0)
            ->update(array('welcomeemail' => $templateId));
    }

    /**
     * Template per language ('' is the default one).
     */
    private static function templates()
    {
        return array(
            '' => array(
                'subject' => 'Your VPS {$service_domain} is ready',
                'message' => self::message(array(
                    'intro'    => 'Your VPS <strong>{$service_domain}</strong> ({$service_product_name}) has been created and is starting up. It takes about a minute before you can log in.',
                    'details'  => 'Access details',
                    'hostname' => 'Hostname',
                    'ipv4'     => 'IPv4',
                    'ipv6'     => 'IPv6',
                    'username' => 'Username',
                    'password' => 'Password',
                    'ssh'      => 'Connect over SSH',
                    'panel'    => 'From the control panel you can reboot the VPS, open the console, reinstall it and manage its firewall, backups and reverse DNS:',
                    'manage'   => 'Manage your VPS',
                    'security' => 'For your security, change the password after your first login or use SSH keys.',
                    'support'  => 'If you need help, open a ticket from the control panel.',
                    'hello'    => 'Hi',
                )),
            ),
            'spanish' => array(
                'subject' => 'Tu VPS {$service_domain} está listo',
                'message' => self::message(array(
                    'intro'    => 'Hemos creado tu VPS <strong>{$service_domain}</strong> ({$service_product_name}) y se está iniciando. En un minuto podrás entrar.',
                    'details'  => 'Datos de acceso',
                    'hostname' => 'Hostname',
                    'ipv4'     => 'IPv4',
                    'ipv6'     => 'IPv6',
                    'username' => 'Usuario',
                    'password' => 'Contraseña',
                    'ssh'      => 'Conectarte por SSH',
                    'panel'    => 'Desde el panel puedes reiniciar el VPS, abrir la consola, reinstalarlo y gestionar el firewall, los backups y el DNS inverso:',
                    'manage'   => 'Gestionar tu VPS',
                    'security' => 'Por seguridad, cambia la contraseña al entrar por primera vez o usa claves SSH.',
                    'support'  => 'Si necesitas ayuda, abre un ticket desde el panel.',
                    'hello'    => 'Hola',
                )),
            ),
        );
    }

    private static function message(array $t)
    {
        $panel = '{$whmcs_url}clientarea.php?action=productdetails&amp;id={$service_id}';

        return <<<HTML
<p>{$t['hello']} {\$client_first_name},</p>
<p>{$t['intro']}</p>
<p><strong>{$t['details']}</strong></p>
<p>
{$t['hostname']}: {\$service_domain}<br />
{if \$service_dedicated_ip}{$t['ipv4']}: {\$service_dedicated_ip}<br />{/if}
{if \$service_assigned_ips}{$t['ipv6']}: {\$service_assigned_ips}<br />{/if}
{$t['username']}: {\$service_username}<br />
{$t['password']}: {\$service_password}
</p>
<p>{$t['ssh']}:<br />
<code>ssh {\$service_username}@{if \$service_dedicated_ip}{\$service_dedicated_ip}{else}{\$service_assigned_ips}{/if}</code></p>
<p>{$t['panel']}<br />
<a href="{$panel}">{$t['manage']}</a></p>
<p>{$t['security']}</p>
<p>{$t['support']}</p>
<p>{\$signature}</p>
HTML;
    }
}
