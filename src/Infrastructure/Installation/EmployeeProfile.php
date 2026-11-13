<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Installation;

use ProcessWire\HookEvent;
use ProcessWire\User;
use ProcessWire\Wire;
use ProcessWire\WireException;

final class EmployeeProfile extends Wire
{
    public static function name(User $user): string
    {
        return (string) ($user->meta('kontorEmployeeName') ?: $user->get('title') ?: $user->name);
    }

    public static function avatar(User $user): string
    {
        $data = $user->meta('kontorEmployeeAvatar');
        return is_string($data) && $data !== '' ? 'data:image/jpeg;base64,' . $data : '';
    }

    public function renderAccount(HookEvent $event): void
    {
        $wire = $this->wire();
        $user = $wire->user;
        if (!$user->isLoggedin() || $user->isLocked() || $user->isUnpublished() || $user->isTrash()) {
            return;
        }

        $url = $wire->modules->get('Workspace')->homeUrl() . 'account/#employee-profile';
        $csrf = $wire->session->CSRF->getToken('kontor-employee-avatar');
        $error = '';
        $action = (string) $wire->input->post('kontor_employee_avatar_action');
        if ($wire->input->requestMethod('POST') && $action !== '') {
            $wire->session->CSRF->validate('kontor-employee-avatar');
            try {
                if ($action === 'upload') {
                    $user->meta()->set('kontorEmployeeAvatar', self::encodeAvatar($_FILES['employee_avatar'] ?? []));
                } elseif ($action === 'remove') {
                    $user->meta()->remove('kontorEmployeeAvatar');
                } else {
                    throw new WireException('Choose an avatar action.');
                }
                $wire->session->redirect($url);
            } catch (WireException $exception) {
                $error = $exception->getMessage();
            }
        }
        $name = self::name($user);
        $avatar = self::avatar($user);
        $e = self::escape(...);
        ob_start();
        require dirname(__DIR__, 3) . '/templates/admin/employee-profile.php';
        $event->return .= ob_get_clean();
    }

    public function addWorkspaceMenu(): void
    {
        $wire = $this->wire();
        $user = $wire->user;
        if (!$wire->adminTheme instanceof \ProcessWire\AdminThemeWorkspace || !$user->isLoggedin()) {
            return;
        }
        $url = $wire->modules->get('Workspace')->homeUrl() . 'account/#employee-profile';
        $asset = $wire->config->urls->get('Kontor') . 'assets/employee-profile.js?v='
            . filemtime(dirname(__DIR__, 3) . '/assets/employee-profile.js');
        $wire->adminTheme->addExtraMarkup('body', '<script defer src="' . self::escape($asset)
            . '" data-profile-url="' . self::escape($url)
            . '" data-display-name="' . self::escape(self::name($user))
            . '" data-avatar="' . self::escape(self::avatar($user)) . '"></script>');
    }

    public static function encodeAvatar(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file($file['tmp_name'] ?? '') || $file['size'] > 5 * 1024 * 1024) {
            throw new WireException('Choose an image up to 5 MB.');
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] > 4096 || $info[1] > 4096) {
            throw new WireException('Use a JPEG, PNG or WebP image up to 4096 pixels per side.');
        }
        if (!extension_loaded('gd')) {
            throw new WireException('Image processing is unavailable.');
        }
        $image = @imagecreatefromstring((string) file_get_contents($file['tmp_name']));
        if (!$image) {
            throw new WireException('This image could not be read.');
        }
        $side = min($info[0], $info[1]);
        $avatar = imagecreatetruecolor(128, 128);
        imagefill($avatar, 0, 0, imagecolorallocate($avatar, 255, 255, 255));
        imagecopyresampled($avatar, $image, 0, 0, intdiv($info[0] - $side, 2), intdiv($info[1] - $side, 2), 128, 128, $side, $side);
        ob_start();
        imagejpeg($avatar, null, 85);
        return base64_encode((string) ob_get_clean());
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
