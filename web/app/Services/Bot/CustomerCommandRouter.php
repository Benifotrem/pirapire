<?php

namespace App\Services\Bot;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use App\Services\Mempool\MempoolClient;
use DomainException;

/**
 * Parses an incoming Telegram message from a customer and, if it's a
 * recognized command (/mempool, /vip, /escrow, /start, /help), returns the
 * reply text. Returns null for non-command messages so the webhook
 * controller can stay silent on casual chat — mirrors the old WhatsApp
 * bot's routeCommand(), now living in PHP instead of a separate Node
 * process.
 */
class CustomerCommandRouter
{
    private const USAGE = <<<'TEXT'
        🔒 *Comandos de Escrow Lightning*

        /escrow create <monto_sats> <descripción> — crea un trabajo (comisión 1.5%)
        /escrow status <id> — consulta el estado de un trabajo
        /escrow release <id> <factura_bolt11> — libera los fondos a la factura del freelancer
        /escrow dispute <id> — abre una disputa para que un admin resuelva
        TEXT;

    public function __construct(
        private readonly EscrowService $escrow,
        private readonly MempoolClient $mempool,
    ) {}

    public function route(Customer $customer, string $text): ?string
    {
        $trimmed = trim($text);
        if (! str_starts_with($trimmed, '/')) {
            return null;
        }

        $parts = preg_split('/\s+/', substr($trimmed, 1)) ?: [''];
        $rawCommand = array_shift($parts);
        $args = $parts;
        $command = strtolower(strtok($rawCommand ?: '', '@')); // strip a possible "@BotName" suffix

        return match ($command) {
            'start' => $this->start(),
            'mempool' => $this->mempool->summary(),
            'vip' => $this->vip($customer),
            'escrow' => $this->escrow($customer, $args),
            'help' => $this->help(),
            default => null,
        };
    }

    private function start(): string
    {
        return implode("\n", [
            '⚡ *Bienvenido a Pirapire*',
            '',
            'Tocá el botón ☰ junto al mensaje para abrir la app completa (alertas, escrow y mempool con botones) — o usá los comandos: /mempool, /vip, /escrow, /help',
        ]);
    }

    private function help(): string
    {
        return implode("\n", [
            '🤖 *Comandos disponibles en Pirapire*',
            '/mempool — altura de bloque y tarifas actuales',
            '/vip — tu estado de suscripción VIP',
            '/escrow — gestión de trabajos con escrow Lightning',
            '',
            'Tip: el botón ☰ junto al mensaje abre la app completa, con formularios en vez de comandos.',
        ]);
    }

    private function vip(Customer $customer): string
    {
        if ($customer->isVip()) {
            $expiry = $customer->vipSubscriptions()
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest('expires_at')
                ->first()
                ?->expires_at
                ?->translatedFormat('d/m/Y');

            return implode("\n", [
                '⭐ *Sos VIP en Pirapire*',
                'Vencimiento: '.($expiry ?? 'sin vencimiento'),
                'Recibís alertas P2P de RoboSats de forma instantánea, sin el retraso de 10 minutos del plan gratuito.',
            ]);
        }

        return implode("\n", [
            '🆓 *Estás en el plan gratuito*',
            'Las alertas P2P de RoboSats te llegan con 10 minutos de retraso.',
            '',
            'Actualizá a VIP para recibir alertas instantáneas y otros beneficios:',
            'https://pirapire.pro/vip',
        ]);
    }

    /** @param string[] $args */
    private function escrow(Customer $customer, array $args): string
    {
        $rest = $args;
        $subcommand = array_shift($rest);
        $rest = array_pad($rest, 3, null); // pad so escrowStatus/Release/Dispute can safely read fixed positions

        return match ($subcommand) {
            'create' => $this->escrowCreate($customer, $rest),
            'status' => $this->escrowStatus($rest),
            'release' => $this->escrowRelease($rest),
            'dispute' => $this->escrowDispute($customer, $rest),
            default => self::USAGE,
        };
    }

    /** @param array<int, string|null> $rest */
    private function escrowCreate(Customer $customer, array $rest): string
    {
        $descParts = $rest;
        $amountRaw = array_shift($descParts);
        $amountSats = (int) $amountRaw;
        $description = trim(implode(' ', array_filter($descParts, fn ($part) => $part !== null)));

        if ($amountSats <= 0 || $description === '') {
            return "Uso: /escrow create <monto_sats> <descripción>\n\n".self::USAGE;
        }

        try {
            $job = $this->escrow->createJob($customer, $amountSats, $description);
        } catch (DomainException $e) {
            return "⚠️ {$e->getMessage()}";
        }

        return implode("\n", [
            '✅ *Trabajo de escrow creado*',
            "ID: {$job->id}",
            "Monto: {$job->amount_sats} sats (comisión {$job->fee_sats} sats)",
            "Estado: {$job->status}",
            '',
            'Factura para financiar el escrow:',
            $job->funding_invoice,
            '',
            'Expira: '.$job->expires_at->translatedFormat('d/m/Y H:i'),
        ]);
    }

    /** @param array<int, string|null> $rest */
    private function escrowStatus(array $rest): string
    {
        [$jobId] = $rest;
        if (! $jobId) {
            return "Uso: /escrow status <id>\n\n".self::USAGE;
        }

        $job = EscrowJob::find($jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        return implode("\n", [
            "📋 *Escrow {$job->id}*",
            "Estado: {$job->status}",
            "Monto: {$job->amount_sats} sats (comisión {$job->fee_sats} sats)",
        ]);
    }

    /** @param array<int, string|null> $rest */
    private function escrowRelease(array $rest): string
    {
        [$jobId, $payoutBolt11] = $rest;
        if (! $jobId || ! $payoutBolt11) {
            return "Uso: /escrow release <id> <factura_bolt11>\n\n".self::USAGE;
        }

        $job = EscrowJob::find($jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->release($job, $payoutBolt11);
        } catch (DomainException) {
            return "⚠️ No se pudo liberar el escrow {$jobId}. Verificá que la factura sea válida y no haya expirado.";
        }

        return "✅ Fondos del escrow {$jobId} liberados.";
    }

    /** @param array<int, string|null> $rest */
    private function escrowDispute(Customer $customer, array $rest): string
    {
        [$jobId] = $rest;
        if (! $jobId) {
            return "Uso: /escrow dispute <id>\n\n".self::USAGE;
        }

        $job = EscrowJob::find($jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->openDispute($job, $customer, 'Disputa abierta desde Telegram');
        } catch (DomainException) {
            return "⚠️ No se pudo abrir la disputa para el trabajo {$jobId}.";
        }

        return "🚩 Disputa abierta para el escrow {$jobId}. Un admin la revisará pronto.";
    }
}
