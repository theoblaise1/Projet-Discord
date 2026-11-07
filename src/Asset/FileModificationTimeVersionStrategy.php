<?php

namespace App\Asset;

use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Ajoute la date de dernière modification du fichier aux URL générées par
 * asset() : /css/app.css devient /css/app.css?v=1762520400.
 *
 * Dès qu'un fichier CSS/JS est modifié, son URL change et le navigateur
 * télécharge la nouvelle version au lieu d'utiliser celle de son cache.
 *
 * Activée dans config/packages/framework.yaml (assets.version_strategy).
 */
final class FileModificationTimeVersionStrategy implements VersionStrategyInterface
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getVersion(string $path): string
    {
        $file = $this->publicDir.'/'.ltrim(parse_url($path, \PHP_URL_PATH) ?: $path, '/');

        return is_file($file) ? (string) filemtime($file) : '';
    }

    public function applyVersion(string $path): string
    {
        $version = $this->getVersion($path);

        if ('' === $version) {
            return $path;
        }

        return $path.(str_contains($path, '?') ? '&' : '?').'v='.$version;
    }
}
