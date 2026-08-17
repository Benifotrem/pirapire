<?php

namespace App\Services\Bot;

use App\Models\Customer;
use App\Models\EscrowJob;
use App\Services\Escrow\EscrowService;
use App\Services\Mempool\MempoolClient;
use DomainException;
use RuntimeException;

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
    private const LNBITS_DOWN_MESSAGE = '⚠️ El proveedor de pagos no está disponible en este momento. Probá de nuevo en unos minutos.';

    public function __construct(
        private readonly EscrowService $escrow,
        private readonly MempoolClient $mempool,
    ) {}

    /** Built at call time (not a class const) so the fee shown always matches ESCROW_FEE_PERCENT, not whatever it was when this file was last edited. */
    private function usage(): string
    {
        $fee = rtrim(rtrim(number_format($this->escrow->feePercent(), 2), '0'), '.');

        return <<<TEXT
            🔒 *Comandos de Escrow Lightning*

            /escrow create <monto_sats> <descripción> — publica un trabajo (comisión {$fee}%)
            /escrow browse — ver trabajos abiertos para postularte
            /escrow apply <id> <mensaje> — postularte a un trabajo abierto
            /escrow applications <id> — ver postulaciones a tu trabajo
            /escrow accept <id_trabajo> <id_postulación> — elegir un freelancer y financiar
            /escrow deliver <id> <factura_bolt11> — marcar entregado y pedir el cobro
            /escrow release <id> — liberar el pago al freelancer
            /escrow dispute <id> [motivo] — abrir una disputa para que un admin resuelva
            /escrow status <id> — consultar el estado de un trabajo
            /escrow cancel <id> — cancelar un trabajo tuyo que aún no fue asignado
            TEXT;
    }

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
        $freeTierDelayMinutes = (int) config('services.alerts.free_tier_delay_minutes');

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
                $freeTierDelayMinutes > 0
                    ? "Recibís alertas P2P de RoboSats de forma instantánea, sin el retraso de {$freeTierDelayMinutes} minutos del plan gratuito."
                    : 'Recibís alertas P2P de RoboSats de forma instantánea.',
            ]);
        }

        return implode("\n", [
            '🆓 *Estás en el plan gratuito*',
            $freeTierDelayMinutes > 0
                ? "Las alertas P2P de RoboSats te llegan con {$freeTierDelayMinutes} minutos de retraso."
                : 'Las alertas P2P de RoboSats te llegan al instante.',
            '',
            $freeTierDelayMinutes > 0 ? 'Actualizá a VIP para recibir alertas instantáneas y otros beneficios:' : 'Conocé los demás beneficios de VIP:',
            'https://pirapire.pro/vip',
        ]);
    }

    /** @param string[] $args */
    private function escrow(Customer $customer, array $args): string
    {
        $rest = $args;
        $subcommand = array_shift($rest);

        return match ($subcommand) {
            'create' => $this->escrowCreate($customer, $rest),
            'browse' => $this->escrowBrowse($customer),
            'apply' => $this->escrowApply($customer, $rest),
            'applications' => $this->escrowApplications($customer, $rest),
            'accept' => $this->escrowAccept($customer, $rest),
            'deliver' => $this->escrowDeliver($customer, $rest),
            'release' => $this->escrowRelease($customer, $rest),
            'dispute' => $this->escrowDispute($customer, $rest),
            'status' => $this->escrowStatus($customer, $rest),
            'cancel' => $this->escrowCancel($customer, $rest),
            default => $this->usage(),
        };
    }

    /**
     * Finds a job the given customer created. Returns null (and the caller
     * should show the same generic "not found" message used for a missing
     * id) for both a nonexistent job and one that belongs to someone else —
     * telling those two cases apart would let a customer probe which job
     * ids exist by trying commands on ids that aren't theirs.
     */
    private function ownedJobOrNull(Customer $customer, ?string $jobId): ?EscrowJob
    {
        $job = $jobId ? EscrowJob::find($jobId) : null;

        return $job && $job->creator_customer_id === $customer->id ? $job : null;
    }

    /** Same idea as ownedJobOrNull(), but for the freelancer assigned to the job. */
    private function assignedJobOrNull(Customer $customer, ?string $jobId): ?EscrowJob
    {
        $job = $jobId ? EscrowJob::find($jobId) : null;

        return $job && $job->counterparty_customer_id === $customer->id ? $job : null;
    }

    /** Same idea, but for either party — used by /status and /dispute, which both sides may call. */
    private function partyJobOrNull(Customer $customer, ?string $jobId): ?EscrowJob
    {
        $job = $jobId ? EscrowJob::find($jobId) : null;
        if (! $job) {
            return null;
        }

        return in_array($customer->id, [$job->creator_customer_id, $job->counterparty_customer_id], true) ? $job : null;
    }

    /** @param array<int, string|null> $rest */
    private function escrowCreate(Customer $customer, array $rest): string
    {
        $descParts = $rest;
        $amountRaw = array_shift($descParts);
        $amountSats = (int) $amountRaw;
        $description = trim(implode(' ', array_filter($descParts, fn ($part) => $part !== null)));

        if ($amountSats <= 0 || $description === '') {
            return "Uso: /escrow create <monto_sats> <descripción>\n\n".$this->usage();
        }

        try {
            $job = $this->escrow->postJob($customer, $amountSats, $description);
        } catch (DomainException $e) {
            return "⚠️ {$e->getMessage()}";
        }

        return implode("\n", [
            '✅ *Trabajo publicado*',
            "ID: {$job->id}",
            "Monto: {$job->amount_sats} sats (comisión {$job->fee_sats} sats)",
            '',
            'Ya es visible para freelancers con /escrow browse. Revisá las postulaciones con:',
            "/escrow applications {$job->id}",
        ]);
    }

    private function escrowBrowse(Customer $customer): string
    {
        $jobs = EscrowJob::where('status', 'open')
            ->where('creator_customer_id', '!=', $customer->id)
            ->latest()
            ->limit(10)
            ->get();

        if ($jobs->isEmpty()) {
            return 'No hay trabajos abiertos en este momento.';
        }

        $lines = ['📋 *Trabajos abiertos*', ''];
        foreach ($jobs as $job) {
            $lines[] = "ID: {$job->id}";
            $lines[] = "Monto: {$job->amount_sats} sats";
            $lines[] = $job->description;
            $lines[] = "Postularte: /escrow apply {$job->id} <tu mensaje>";
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /** @param array<int, string|null> $rest */
    private function escrowApply(Customer $customer, array $rest): string
    {
        $parts = $rest;
        $jobId = array_shift($parts);
        $message = trim(implode(' ', array_filter($parts, fn ($part) => $part !== null)));

        if (! $jobId || $message === '') {
            return "Uso: /escrow apply <id> <mensaje>\n\n".$this->usage();
        }

        $job = EscrowJob::where('status', 'open')->find($jobId);
        if (! $job) {
            return "⚠️ No se encontró un trabajo abierto con ID {$jobId}.";
        }

        try {
            $this->escrow->applyToJob($job, $customer, $message);
        } catch (DomainException $e) {
            return "⚠️ {$e->getMessage()}";
        }

        return "✅ Te postulaste al trabajo {$jobId}. El cliente va a revisar tu postulación.";
    }

    /** @param array<int, string|null> $rest */
    private function escrowApplications(Customer $customer, array $rest): string
    {
        [$jobId] = array_pad($rest, 1, null);
        if (! $jobId) {
            return "Uso: /escrow applications <id>\n\n".$this->usage();
        }

        $job = $this->ownedJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        $applications = $job->applications()->where('status', 'pending')->get();
        if ($applications->isEmpty()) {
            return 'Todavía no hay postulaciones para este trabajo.';
        }

        $lines = ["📋 *Postulaciones para {$jobId}*", ''];
        foreach ($applications as $application) {
            $lines[] = 'Postulación #'.$application->id.' — '.($application->freelancer->display_name ?? "Freelancer #{$application->freelancer_customer_id}");
            $lines[] = $application->message;
            $lines[] = "Aceptar: /escrow accept {$jobId} {$application->id}";
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /** @param array<int, string|null> $rest */
    private function escrowAccept(Customer $customer, array $rest): string
    {
        [$jobId, $applicationId] = array_pad($rest, 2, null);
        if (! $jobId || ! $applicationId) {
            return "Uso: /escrow accept <id_trabajo> <id_postulación>\n\n".$this->usage();
        }

        $job = $this->ownedJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        $application = $job->applications()->find($applicationId);
        if (! $application) {
            return "⚠️ No se encontró la postulación {$applicationId}.";
        }

        try {
            $job = $this->escrow->acceptApplication($application, $customer);
        } catch (DomainException $e) {
            return "⚠️ {$e->getMessage()}";
        } catch (RuntimeException) {
            return self::LNBITS_DOWN_MESSAGE;
        }

        return implode("\n", [
            '✅ *Freelancer asignado*',
            'Factura para financiar el escrow:',
            $job->funding_invoice,
            '',
            'Expira: '.$job->expires_at->translatedFormat('d/m/Y H:i'),
        ]);
    }

    /** @param array<int, string|null> $rest */
    private function escrowDeliver(Customer $customer, array $rest): string
    {
        [$jobId, $bolt11] = array_pad($rest, 2, null);
        if (! $jobId || ! $bolt11) {
            return "Uso: /escrow deliver <id> <factura_bolt11>\n\n".$this->usage();
        }

        $job = $this->assignedJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->deliver($job, $customer, $bolt11);
        } catch (DomainException $e) {
            return "⚠️ {$e->getMessage()}";
        }

        return "✅ Marcaste el trabajo {$jobId} como entregado. El cliente ya puede liberar el pago.";
    }

    /** @param array<int, string|null> $rest */
    private function escrowRelease(Customer $customer, array $rest): string
    {
        [$jobId] = array_pad($rest, 1, null);
        if (! $jobId) {
            return "Uso: /escrow release <id>\n\n".$this->usage();
        }

        $job = $this->ownedJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->release($job, $customer);
        } catch (DomainException $e) {
            return "⚠️ No se pudo liberar el escrow {$jobId}: {$e->getMessage()}";
        } catch (RuntimeException) {
            return self::LNBITS_DOWN_MESSAGE;
        }

        return "✅ Fondos del escrow {$jobId} liberados.";
    }

    /** @param array<int, string|null> $rest */
    private function escrowDispute(Customer $customer, array $rest): string
    {
        $parts = $rest;
        $jobId = array_shift($parts);
        $reason = trim(implode(' ', array_filter($parts, fn ($part) => $part !== null)));
        $reason = $reason !== '' ? $reason : 'Disputa abierta desde Telegram';

        if (! $jobId) {
            return "Uso: /escrow dispute <id> [motivo]\n\n".$this->usage();
        }

        $job = $this->partyJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->openDispute($job, $customer, $reason);
        } catch (DomainException $e) {
            return "⚠️ No se pudo abrir la disputa para el trabajo {$jobId}: {$e->getMessage()}";
        }

        return "🚩 Disputa abierta para el escrow {$jobId}. Un admin la revisará pronto.";
    }

    /** @param array<int, string|null> $rest */
    private function escrowStatus(Customer $customer, array $rest): string
    {
        [$jobId] = array_pad($rest, 1, null);
        if (! $jobId) {
            return "Uso: /escrow status <id>\n\n".$this->usage();
        }

        $job = $this->partyJobOrNull($customer, $jobId);
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
    private function escrowCancel(Customer $customer, array $rest): string
    {
        [$jobId] = array_pad($rest, 1, null);
        if (! $jobId) {
            return "Uso: /escrow cancel <id>\n\n".$this->usage();
        }

        $job = $this->ownedJobOrNull($customer, $jobId);
        if (! $job) {
            return "⚠️ No se encontró el trabajo {$jobId}.";
        }

        try {
            $this->escrow->cancelOpenJob($job, $customer);
        } catch (DomainException $e) {
            return "⚠️ No se pudo cancelar el trabajo {$jobId}: {$e->getMessage()}";
        }

        return "✅ Trabajo {$jobId} cancelado.";
    }
}
