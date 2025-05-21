<?php

namespace App\Services\Security;

use App\Models\UserSecurityLog;

class DeviceDetector
{
    /**
     * Генерує унікальний ідентифікатор пристрою на основі User-Agent та інших параметрів.
     */
    public function generateDeviceId(string $userAgent, string $ipAddress): string
    {
        $data = $userAgent . $ipAddress;
        return hash('sha256', $data);
    }

    /**
     * Отримує інформацію про пристрій з User-Agent.
     */
    public function getDeviceInfo(string $userAgent): array
    {
        $deviceType = $this->detectDeviceType($userAgent);
        $os = $this->detectOperatingSystem($userAgent);
        $browser = $this->detectBrowser($userAgent);

        return [
            'type' => $deviceType,
            'os' => $os,
            'browser' => $browser,
            'user_agent' => $userAgent
        ];
    }

    /**
     * Виявляє тип пристрою з User-Agent.
     */
    private function detectDeviceType(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        if (preg_match('/(tablet|ipad|playbook)/i', $userAgent)) {
            return 'tablet';
        }

        if (preg_match('/(mobile|iphone|ipod|android|blackberry|opera mini|iemobile)/i', $userAgent)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Виявляє операційну систему з User-Agent.
     */
    private function detectOperatingSystem(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        $osPatterns = [
            'windows nt 10' => 'Windows 10',
            'windows nt 6.3' => 'Windows 8.1',
            'windows nt 6.2' => 'Windows 8',
            'windows nt 6.1' => 'Windows 7',
            'windows nt 6.0' => 'Windows Vista',
            'windows nt 5.2' => 'Windows Server 2003/XP x64',
            'windows nt 5.1' => 'Windows XP',
            'windows xp' => 'Windows XP',
            'windows nt 5.0' => 'Windows 2000',
            'windows me' => 'Windows ME',
            'win98' => 'Windows 98',
            'win95' => 'Windows 95',
            'win16' => 'Windows 3.11',
            'macintosh|mac os x' => 'macOS',
            'mac_powerpc' => 'Mac OS 9',
            'ubuntu' => 'Ubuntu',
            'linux' => 'Linux',
            'iphone' => 'iOS',
            'ipad' => 'iOS',
            'android' => 'Android',
            'blackberry' => 'BlackBerry',
            'webos' => 'Mobile'
        ];

        foreach ($osPatterns as $pattern => $os) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                return $os;
            }
        }

        return 'Unknown OS';
    }

    /**
     * Виявляє браузер з User-Agent.
     */
    private function detectBrowser(string $userAgent): string
    {
        $userAgent = strtolower($userAgent);

        $browserPatterns = [
            'msie|trident' => 'Internet Explorer',
            'firefox' => 'Firefox',
            'chrome' => 'Chrome',
            'safari' => 'Safari',
            'opera' => 'Opera',
            'netscape' => 'Netscape',
            'maxthon' => 'Maxthon',
            'konqueror' => 'Konqueror',
            'edge' => 'Edge'
        ];

        foreach ($browserPatterns as $pattern => $browser) {
            if (preg_match('/' . $pattern . '/i', $userAgent)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }

    /**
     * Перевіряє, чи є пристрій новим для користувача.
     */
    public function isNewDevice(int $userId, string $deviceId): bool
    {
        $count = UserSecurityLog
            ::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->count();

        return $count === 0;
    }
}
