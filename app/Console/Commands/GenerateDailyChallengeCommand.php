<?php

namespace App\Console\Commands;

use App\Services\DailyChallengeGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

#[Signature('daily:challenge {--force : Replace today\'s challenge if it already exists}')]
#[Description('Generate today\'s daily challenge quiz from published GAT questions')]
class GenerateDailyChallengeCommand extends Command
{
    public function handle(DailyChallengeGenerator $generator): int
    {
        try {
            $result = $generator->generate(force: (bool) $this->option('force'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error('Failed to generate daily challenge: '.$exception->getMessage());

            return self::FAILURE;
        }

        $challenge = $result['challenge'];
        $action = $result['created'] ? 'Created' : 'Using';

        $this->info(sprintf(
            '%s daily challenge for %s (%d questions).',
            $action,
            $challenge->challenge_date->toDateString(),
            count($challenge->questions ?? []),
        ));

        return self::SUCCESS;
    }
}
