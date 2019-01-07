<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Flex\Configurator;

use Symfony\Flex\Recipe;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CopyFromRecipeConfigurator extends AbstractConfigurator
{
    public function configure(Recipe $recipe, $config, array $options = [])
    {
        $this->write('Setting configuration and copying files');
        $this->copyFiles($config, $recipe->getFiles(), $this->options->get('root-dir'), $options['force'] ?? false);
    }

    public function unconfigure(Recipe $recipe, $config)
    {
        $this->write('Removing configuration and files');
        $this->removeFiles($config, $recipe->getFiles(), $this->options->get('root-dir'));
    }

    private function copyFiles(array $manifest, array $files, string $to, bool $overwrite = false)
    {
        foreach ($manifest as $source => $target) {
            $target = $this->options->expandTargetDir($target);
            if ('/' === substr($source, -1)) {
                $this->copyDir($source, $this->path->concatenate([$to, $target]), $files, $overwrite);
            } else {
                $this->copyFile($this->path->concatenate([$to, $target]), $files[$source]['contents'], $files[$source]['executable'], $overwrite);
            }
        }
    }

    private function copyDir(string $source, string $target, array $files, bool $overwrite = false)
    {
        foreach ($files as $file => $data) {
            if (0 === strpos($file, $source)) {
                $file = $this->path->concatenate([$target, substr($file, \strlen($source))]);
                $this->copyFile($file, $data['contents'], $data['executable'], $overwrite);
            }
        }
    }

    private function copyFile(string $to, string $contents, bool $executable, bool $overwrite = false)
    {
        if (!$this->options->shouldWriteFile($to, $overwrite)) {
            return;
        }

        if (!is_dir(\dirname($to))) {
            mkdir(\dirname($to), 0777, true);
        }

        file_put_contents($to, $this->options->expandTargetDir($contents));
        if ($executable) {
            @chmod($to, fileperms($to) | 0111);
        }

        $this->write(sprintf('Created <fg=green>"%s"</>', $this->path->relativize($to)));
    }

    private function removeFiles(array $manifest, array $files, string $to)
    {
        foreach ($manifest as $source => $target) {
            $target = $this->options->expandTargetDir($target);

            if ('.git' === $target) {
                // never remove the main Git directory, even if it was created by a recipe
                continue;
            }

            if ('/' === substr($source, -1)) {
                foreach (array_keys($files) as $file) {
                    if (0 === strpos($file, $source)) {
                        $this->removeFile($this->path->concatenate([$to, $target, substr($file, \strlen($source))]));
                    }
                }
            } else {
                $this->removeFile($this->path->concatenate([$to, $target]));
            }
        }
    }

    private function removeFile(string $to)
    {
        if (!file_exists($to)) {
            return;
        }

        @unlink($to);
        $this->write(sprintf('Removed <fg=green>"%s"</>', $this->path->relativize($to)));

        if (0 === \count(glob(\dirname($to).'/*', GLOB_NOSORT))) {
            @rmdir(\dirname($to));
        }
    }
}
