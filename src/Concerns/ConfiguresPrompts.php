<?php

namespace Laravel\Boost\Concerns;

use Laravel\Prompts\MultiSelectPrompt;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\SelectPrompt;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ConfiguresPrompts
{
    protected function configurePrompts(InputInterface $input, ?OutputInterface $output = null): void
    {
        Prompt::fallbackWhen(! $input->isInteractive() || PHP_OS_FAMILY === 'Windows');

        SelectPrompt::fallbackUsing(fn (SelectPrompt $prompt) => $this->promptUntilValid(
            fn (): mixed => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, $prompt->default),
            false,
            $prompt->validate,
            $output
        ));

        MultiSelectPrompt::fallbackUsing(function (MultiSelectPrompt $prompt) use ($input, $output) {
            if ($prompt->default !== []) {
                return $this->promptUntilValid(
                    fn (): mixed => (new SymfonyStyle($input, $output))->choice($prompt->label, $prompt->options, implode(',', $prompt->default), true),
                    $prompt->required,
                    $prompt->validate,
                    $output
                );
            }

            return $this->promptUntilValid(
                fn () => collect((new SymfonyStyle($input, $output))->choice(
                    $prompt->label,
                    array_is_list($prompt->options)
                        ? ['None', ...$prompt->options]
                        : ['none' => 'None', ...$prompt->options],
                    'None',
                    true)
                )->reject(array_is_list($prompt->options) ? 'None' : 'none')->all(),
                $prompt->required,
                $prompt->validate,
                $output
            );
        });
    }

    protected function promptUntilValid(mixed $prompt, mixed $required = false, mixed $validate = null, ?OutputInterface $output = null): mixed
    {
        while (true) {
            $result = $prompt();

            if ($required && ($result === '' || $result === [] || $result === false)) {
                $output?->writeln('<error>'.(is_string($required) ? $required : 'Required.').'</error>');

                continue;
            }

            if ($validate) {
                $error = $validate($result);

                if (is_string($error) && strlen($error) > 0) {
                    $output?->writeln("<error>{$error}</error>");

                    continue;
                }
            }

            return $result;
        }
    }
}
