<?php

return [

    'meta_title' => 'Help & FAQ — Pirapire.pro',
    'meta_description' => 'Step-by-step usage manual and frequently asked questions about Pirapire.pro: sovereign login, P2P alerts, hiring and working with Lightning Escrow, and more.',

    'hero_badge' => 'Help center',
    'hero_title' => 'Usage manual and frequently asked questions',
    'hero_subtitle' => 'Everything you need to use Pirapire.pro, step by step. Pick a guide below or search for your question directly.',
    'search_placeholder' => 'Search the FAQ… (e.g. "fee", "dispute", "Telegram")',
    'search_no_results' => "We couldn't find any question matching your search.",

    'nav_manual' => 'Usage manual',
    'nav_faq' => 'Frequently asked questions',

    'manual_title' => 'Step-by-step manual',
    'manual_subtitle' => "Pick what you want to do — each guide stands on its own, you don't need to read them in order.",

    'tabs' => [
        'login' => '⚡ Log in',
        'alerts' => '🔔 P2P Alerts',
        'telegram' => '📨 Link Telegram',
        'hire' => '💼 Hire (client)',
        'freelance' => '🛠️ Work and get paid',
        'commands' => '🤖 Bot commands',
    ],

    'login' => [
        'title' => 'Log in with your Lightning wallet',
        'intro' => "Pirapire.pro has no email/password signup. Your identity is your Lightning wallet's public key — the standard is called <code>LNURL-auth</code>.",
        'steps' => [
            ['title' => 'Go to the login page', 'body' => 'Open <a href="/login" class="text-blue-600 underline">pirapire.pro/login</a>. You\'ll see a QR code generated on the spot.'],
            ['title' => 'Open your Lightning wallet', 'body' => 'Any wallet compatible with LNURL-auth works: Phoenix, Blink, Zeus, Alby, among others. If you\'re on the same phone, tap the "Open in wallet" button instead of scanning.'],
            ['title' => 'Scan the QR code', 'body' => "Your wallet will show a screen asking you to confirm the login — you won't be asked to sign a payment, and no sats move at this step."],
            ['title' => 'Confirm in your wallet', 'body' => "Once you accept, your wallet signs a challenge with your linking key and sends it to the server. The pirapire.pro page detects the confirmation on its own (it polls every 2 seconds) and redirects you to your dashboard — there's nothing else you need to do."],
        ],
        'tip' => "Since there's no email or password, there's also no 'recover account' — your wallet key is your identity. If you lose access to that wallet, you lose access to that account. Keep your seed phrase safe.",
    ],

    'alerts' => [
        'title' => 'Set up P2P alerts',
        'intro' => "Alerts notify you over Telegram whenever a P2P buy/sell order (on RoboSats or Mostro) matches what you're looking for.",
        'steps' => [
            ['title' => 'Log in', 'body' => 'Sign in with your wallet (see the "Log in" guide) and go to your dashboard (<a href="/dashboard" class="text-blue-600 underline">/dashboard</a>).'],
            ['title' => 'Fill in the "New P2P alert" form', 'body' => 'Pick the currency (PYG or USD), the source (RoboSats, Mostro, or both), the order type (buy, sell, or any), and, optionally, a minimum/maximum amount range.'],
            ['title' => 'The minimum/maximum amount is in the currency you picked, not sats', 'body' => "If you chose PYG, those numbers are guaraníes; if you chose USD, they're dollars. It's the P2P order's fiat amount — not a quantity of satoshis."],
            ['title' => 'Create the alert', 'body' => 'From then on, every time a new matching order shows up, you get a Telegram message with the details and a link or command to take it.'],
            ['title' => 'Pause, activate, or delete anytime', 'body' => 'From "My alerts" on the same dashboard you can temporarily pause an alert, reactivate it, or delete it entirely.'],
        ],
        'tip' => "On the free plan, alerts arrive with a 10-minute delay. With VIP, they arrive instantly. To receive any alert at all, you need Telegram linked to your account — see the 'Link Telegram' guide.",
    ],

    'telegram' => [
        'title' => 'Link your account with Telegram',
        'intro' => "If you logged in with your wallet, your account doesn't have a Telegram chat attached yet — without linking it, you won't receive alerts or notifications. Just messaging <code>/start</code> to the bot on its own isn't enough: that creates a brand new, empty account instead of completing yours.",
        'steps' => [
            ['title' => 'Go to your dashboard', 'body' => 'In <a href="/dashboard" class="text-blue-600 underline">/dashboard</a>, if you haven\'t linked Telegram yet you\'ll see a notice with a "Link now →" link.'],
            ['title' => 'A one-time code is generated', 'body' => 'The page shows you a 6-character code, valid for 10 minutes (e.g. <code>/vincular AB12CD</code>).'],
            ['title' => 'Send that exact message to the BØLT bot', 'body' => 'Open the Telegram chat with BØLT and type the full command exactly as shown on the page.'],
            ['title' => 'Done — the page updates itself', 'body' => 'As soon as the bot receives the code, the page (which is waiting for confirmation) automatically redirects you to your dashboard, now with Telegram linked.'],
        ],
        'tip' => "If you'd already messaged /start to the bot before linking this way, no problem — the system automatically folds that empty conversation into your real account, as long as that conversation doesn't already have alerts or jobs of its own (in that case it'll ask you to sort it out manually, so nothing gets lost).",
    ],

    'hire' => [
        'title' => 'Hire a job (as a client)',
        'intro' => "The entire hiring, payment, and dispute-resolution process happens inside Pirapire — there's no need to agree on anything outside the platform.",
        'steps' => [
            ['title' => 'Post the job', 'body' => 'From the <a href="/dashboard/escrow" class="text-blue-600 underline">job board</a> (or with the command <code>/escrow create &lt;amount_sats&gt; &lt;description&gt;</code> on Telegram), state how many sats you\'ll pay and describe the task. Nothing is charged at this step yet — there\'s no invoice until you pick someone.'],
            ['title' => 'Wait for applications', 'body' => 'Freelancers see your job on the board and apply with a message explaining why they\'re the right fit. You\'ll see them under "Jobs I posted".'],
            ['title' => 'Pick a freelancer', 'body' => 'Review the applications and accept one. At that point the funding invoice is generated — for the amount you offered (plus the platform fee, currently 0%).'],
            ['title' => 'Pay the funding invoice', 'body' => 'Pay it from your own Lightning wallet. The funds are held in the platform\'s wallet until the job is completed.'],
            ['title' => 'Wait for delivery', 'body' => 'When the freelancer is done, they mark the job as delivered and submit their payout invoice — sometimes also a screenshot or other proof, if the job calls for it.'],
            ['title' => 'Review and release the payment', 'body' => "If the work checks out, release the payment with one click — it pays the freelancer automatically. If there's a problem, open a dispute instead of releasing."],
        ],
        'tip' => "You can do this entire process from the web dashboard, the Telegram Mini App, or the bot commands — it's the same job board; posting or applying from any one of the three shows up the same way in the other two.",
    ],

    'freelance' => [
        'title' => 'Work and get paid (as a freelancer)',
        'intro' => 'Browse open jobs, apply, and get paid straight to your Lightning wallet once the client releases the payment.',
        'steps' => [
            ['title' => 'Browse open jobs', 'body' => 'On the <a href="/dashboard/escrow" class="text-blue-600 underline">job board</a> (or with <code>/escrow browse</code> on Telegram) you\'ll see jobs posted by other clients. They also rotate through the site\'s LED ticker, visible without being logged in.'],
            ['title' => 'Apply', 'body' => "Tell the client in a short message why you're the right person for the job."],
            ['title' => 'Wait to be picked', 'body' => 'If the client accepts you, the job moves to "funded" as soon as they pay the invoice — that\'s when you should start the work.'],
            ['title' => 'Do the work and mark it delivered', 'body' => 'When you\'re done, submit your own Lightning invoice (bolt11) to get paid. If the job calls for it (e.g. "share this post and upload a screenshot"), you can attach an image as proof.'],
            ['title' => 'Get paid', 'body' => 'Once the client confirms and releases the payment, your invoice gets paid automatically — no need to chase it or resend it.'],
        ],
        'tip' => "If the client won't release payment without a good reason, or there's any disagreement, you can also open a dispute for an admin to review — it doesn't depend solely on the client acting.",
    ],

    'commands' => [
        'title' => 'BØLT bot commands on Telegram',
        'intro' => 'Everything you can do from the web dashboard can also be done by text command, talking directly to the bot.',
        'groups' => [
            [
                'label' => 'General',
                'items' => [
                    ['cmd' => '/start', 'desc' => 'Registers your chat and shows the welcome message.'],
                    ['cmd' => '/mempool', 'desc' => 'Current block height and recommended fees.'],
                    ['cmd' => '/vip', 'desc' => 'Your VIP subscription status.'],
                    ['cmd' => '/help', 'desc' => 'List of available commands.'],
                ],
            ],
            [
                'label' => 'Escrow (hiring and working)',
                'items' => [
                    ['cmd' => '/escrow create &lt;amount_sats&gt; &lt;description&gt;', 'desc' => 'Posts an open job.'],
                    ['cmd' => '/escrow browse', 'desc' => "Lists other clients' open jobs."],
                    ['cmd' => '/escrow apply &lt;id&gt; &lt;message&gt;', 'desc' => 'Apply to an open job.'],
                    ['cmd' => '/escrow applications &lt;id&gt;', 'desc' => 'View applications to your job.'],
                    ['cmd' => '/escrow accept &lt;job_id&gt; &lt;application_id&gt;', 'desc' => 'Pick a freelancer — generates the funding invoice.'],
                    ['cmd' => '/escrow deliver &lt;id&gt; &lt;bolt11&gt;', 'desc' => 'Mark the job delivered and submit your payout invoice.'],
                    ['cmd' => '/escrow release &lt;id&gt;', 'desc' => 'Release the payment to the freelancer.'],
                    ['cmd' => '/escrow dispute &lt;id&gt; [reason]', 'desc' => 'Open a dispute for an admin to review.'],
                    ['cmd' => '/escrow status &lt;id&gt;', 'desc' => "Check a job's status."],
                    ['cmd' => '/escrow cancel &lt;id&gt;', 'desc' => "Cancel one of your own jobs that doesn't have a freelancer assigned yet."],
                ],
            ],
        ],
        'tip' => "Uploading delivery proof (a screenshot) is only available from the web dashboard or the Mini App — text commands can't attach images.",
    ],

    'faq_title' => 'Frequently asked questions',
    'faq_subtitle' => "If your question isn't here, check the manual above or reach out to us.",

    'faq_categories' => [
        'account' => [
            'label' => 'Account and access',
            'items' => [
                ['q' => 'Do I need an email or password to use Pirapire?', 'a' => "No. Your identity is your Lightning wallet's public key (LNURL-auth). There's no password database to ever leak."],
                ['q' => 'Which Lightning wallets can I use?', 'a' => 'Any wallet compatible with the LNURL-auth standard: Phoenix, Blink, Zeus, Alby, among others.'],
                ['q' => 'I lost access to my wallet — can I recover my account?', 'a' => "There's no email-based 'recover account' mechanism, by design — your wallet key is your identity. Keep your seed phrase safe; it's the same responsibility you already have with any Bitcoin wallet."],
                ['q' => "I sent /start to the bot and my Telegram didn't get linked. Why?", 'a' => "Sending a bare /start only works if your account didn't exist yet. If you already signed up with your wallet, you need to use the 'Link now' button on your dashboard, which gives you a code to send the bot (/vincular CODE) — that connects it to the right account instead of creating a new one."],
            ],
        ],
        'alerts' => [
            'label' => 'P2P Alerts',
            'items' => [
                ['q' => "What's the difference between the free plan and VIP?", 'a' => 'On the free plan, alerts arrive with a 10-minute delay. With VIP, they arrive instantly, as soon as the order appears.'],
                ['q' => 'I set a minimum/maximum amount — what currency is it in?', 'a' => "It's in whatever currency you picked for that alert (PYG or USD) — it's the P2P order's fiat amount, not a quantity of satoshis."],
                ['q' => 'Where do the P2P offers come from?', 'a' => 'From RoboSats and Mostro. You can pick a specific source or "all" when creating your alert.'],
                ['q' => 'How do I get the VIP plan?', 'a' => "It's currently assigned manually — message us on Telegram or contact an admin."],
            ],
        ],
        'escrow' => [
            'label' => 'Hiring and working (Escrow)',
            'items' => [
                ['q' => 'Who holds the money while the job is being done?', 'a' => "The funds sit in the platform's Lightning wallet (a custodial escrow) from the moment the client pays the funding invoice until the payment is released or a dispute is resolved."],
                ['q' => 'How much does Pirapire charge in fees?', 'a' => 'Currently 0% — the platform is in a community validation phase ("Community Mode"). That percentage may change in the future; the current rate is always shown before you post a job.'],
                ['q' => "What happens if the freelancer doesn't deliver the job?", 'a' => 'The client (or the freelancer, if the problem is the other way around) can open a dispute. An admin reviews it and decides whether the payment gets released to the freelancer or refunded to the client.'],
                ['q' => "What if the client won't release the payment for no reason?", 'a' => "The freelancer can also open a dispute once they've delivered the job — it doesn't depend solely on the client acting."],
                ['q' => 'Can I cancel a job I posted?', 'a' => 'Yes, as long as it\'s still "open" (no freelancer assigned yet). Once you\'ve accepted someone and the funding invoice was generated, you can no longer cancel it yourself — if it never gets paid, it cancels automatically once it expires.'],
                ['q' => 'Do I have to use Telegram for all of this?', 'a' => "No. The same job board is available from the web dashboard (no Telegram needed), the Telegram Mini App, and the bot's text commands — posting, applying to, or managing a job from any one of the three shows up the same way in the other two."],
                ['q' => 'What is "delivery proof"?', 'a' => 'For jobs where the deliverable is a verifiable action (e.g. "share this post"), the freelancer can attach a screenshot or other image when marking the job delivered, for the client to review before releasing payment. It\'s optional and only available from the web dashboard or the Mini App.'],
                ['q' => 'Where can I see available jobs without an account?', 'a' => "Open jobs rotate through the site header's LED ticker, visible to any visitor. Clicking it takes you to the job board — if you're not logged in, it invites you to do so first."],
            ],
        ],
        'payments' => [
            'label' => 'Payments and wallet',
            'items' => [
                ['q' => 'Is this a real hold invoice, with funds "held" in an HTLC?', 'a' => "No. LNbits (the software the platform runs on) doesn't have that feature. It's a classic custodial escrow: when you pay the funding invoice, the payment settles immediately into the platform's wallet, which holds it until release or refund."],
                ['q' => "What happens if I don't pay the funding invoice in time?", 'a' => 'The assignment cancels automatically once it expires (usually within an hour), and the job becomes available again for the client to pick another freelancer.'],
            ],
        ],
        'security' => [
            'label' => 'Security and transparency',
            'items' => [
                ['q' => "Is Pirapire's code auditable?", 'a' => 'Yes, the repository is public specifically so anyone can review it before trusting the platform with their sats.'],
                ['q' => 'How do I report a security issue?', 'a' => "Follow the instructions in the repository's SECURITY.md file for responsible disclosure."],
            ],
        ],
    ],

];
