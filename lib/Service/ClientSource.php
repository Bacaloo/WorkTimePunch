<?php

declare(strict_types=1);

namespace OCA\WorkTimePunch\Service;

use OCP\IRequest;

/** Request provenance only; never an authentication or authorization decision. */
final class ClientSource {
	public const UNKNOWN = 'unknown';
	public const NEXTCLOUD = 'nextcloud';
	public const WEB = 'web';
	public const MOBILE = 'mobile';

	public static function fromRequest(IRequest $request): string {
		$agent = trim($request->getHeader('User-Agent'));
		if (preg_match('~^WorkTimePunchWeb/[0-9]~i', $agent)) {
			return self::WEB;
		}
		// Existing Android releases use HttpURLConnection's Dalvik user agent.
		// A browser on Android remains Nextcloud, not the native mobile app.
		if (preg_match('~^Dalvik/[0-9].*\\bAndroid\\b~i', $agent)
			&& strtolower($request->getHeader('OCS-APIRequest')) === 'true') {
			return self::MOBILE;
		}
		if ($request->getHeader('requesttoken') !== ''
			&& strtolower($request->getHeader('X-Requested-With')) === 'xmlhttprequest') {
			return self::NEXTCLOUD;
		}
		return self::UNKNOWN;
	}

	public static function normalize(string $source): string {
		return in_array($source, [self::NEXTCLOUD, self::WEB, self::MOBILE], true) ? $source : self::UNKNOWN;
	}

	public static function label(string $source): string {
		return match (self::normalize($source)) {
			self::NEXTCLOUD => 'Nextcloud',
			self::WEB => 'WEB-GUI',
			self::MOBILE => 'mobile APP',
			default => 'nicht ermittelbar',
		};
	}

	public static function description(?string $startedWith, string $completedWith): string {
		$start = self::normalize($startedWith ?? self::UNKNOWN);
		$end = self::normalize($completedWith);
		if ($start === $end) {
			return 'WorkTimePunch | Client: ' . self::label($end);
		}
		return 'WorkTimePunch | Beginn: ' . self::label($start) . ' | Abschluss: ' . self::label($end);
	}
}
