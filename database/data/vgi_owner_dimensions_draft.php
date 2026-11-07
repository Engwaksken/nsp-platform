<?php

/*
| PROVISIONAL Venture Growth Index rubric using the owner's ten dimensions (M03).
|
| The ten dimension keys and titles are fixed by the owner (App\Growth\OwnerDimensions).
| Everything else here is a PROPOSAL for Hive staff and the owner to review:
| - the questions, answer options and their scores (0 = not started … 100 = strong);
| - the dimension weights: EQUAL by default and NOT approved by the owner;
| - the stage bands (cut-offs) and the insufficient-information rules;
| - there is deliberately NO "readiness" block: investment and commercialization
|   readiness thresholds are an open owner decision, so those indicators show
|   "not configured" until a rubric version defines them.
| It is seeded as a draft. Founders see nothing until Hive approves a version,
| and results stay labelled "Provisional methodology" until the owner signs off.
*/

$unknown = fn (string $tip) => ['key' => 'unknown', 'label' => 'We don’t know yet', 'insufficient' => true, 'tip' => $tip];

$q = fn (string $key, string $prompt, array $options, string $evidence = 'optional', string $evidenceHint = '', string $help = '', ?array $unknownOption = null) => [
    'key' => $key, 'prompt' => $prompt, 'help' => $help, 'weight' => 1, 'evidence' => $evidence, 'evidence_hint' => $evidenceHint,
    'options' => array_merge(
        array_map(fn ($o) => ['key' => $o[0], 'label' => $o[1], 'score' => $o[2], 'tip' => $o[3] ?? null], $options),
        $unknownOption ? [$unknownOption] : [],
    ),
];

$anchors = fn (string $low, string $mid, string $high) => ['0' => $low, '50' => $mid, '100' => $high];

return [
    'meta' => [
        'methodology' => 'provisional',
        'weights' => 'Equal weights (10 each) by default. Not approved by the owner.',
        'bands' => 'Stage cut-offs are a proposal. Not approved by the owner.',
        'readiness' => 'Not configured: investment and commercialization readiness thresholds await an owner decision.',
    ],
    'dimensions' => [
        ['key' => 'problem_clarity', 'title' => 'Problem Clarity', 'weight' => 10,
            'description' => 'How precisely you understand the problem, who has it and why it matters to them.',
            'anchors' => $anchors('The problem is assumed, not described.', 'The problem is written down and partly checked with people who have it.', 'The problem is precise, sized and confirmed by the people who have it.'),
            'questions' => [
                $q('problem_statement', 'Can you describe the problem in one or two sentences, including who has it?', [
                    ['a', 'Not yet', 0, 'Write a one-sentence problem statement: who has the problem, what it is, and what it costs them.'],
                    ['b', 'Yes, but it is broad or changes often', 40, 'Narrow the statement to one group of people and one situation; test it with five of them.'],
                    ['c', 'Yes, written and stable', 80],
                    ['d', 'Yes, written, stable and confirmed by people who have it', 100],
                ], 'optional', 'Your written problem statement, or where the team keeps it.', '', $unknown('Agree as a team on one written problem statement this week.')),
                $q('problem_evidence', 'How do you know the problem is real and painful?', [
                    ['a', 'It is our own idea or experience only', 10, 'Talk to at least 10 people who have the problem and record how they cope today.'],
                    ['b', 'A few conversations', 45, 'Reach 20 or more conversations and note how often the problem happens and what it costs.'],
                    ['c', 'Many conversations or data showing frequency and cost', 85],
                    ['d', 'Documented findings from interviews plus outside data', 100],
                ], 'required', 'Interview notes, survey summary or data source (name or link). Not needed if you answered “our own idea”.', '', $unknown('Start a simple log of every conversation about the problem.')),
            ]],
        ['key' => 'customer_validation', 'title' => 'Customer Validation', 'weight' => 10,
            'description' => 'Proof that real customers want your solution and will use or pay for it.',
            'anchors' => $anchors('No customer has tried or committed to anything.', 'Some customers tried it or committed in principle.', 'Customers repeatedly use and pay, and say why.'),
            'questions' => [
                $q('customer_interviews', 'How many potential customers have given feedback on your solution (not just the problem)?', [
                    ['a', 'None yet', 0, 'Show a mock-up or pilot to at least 10 potential customers and write down their reactions.'],
                    ['b', 'Fewer than 10', 35, 'Reach 20 or more and look for the reasons people would or would not buy.'],
                    ['c', '10 to 50, with notes', 75],
                    ['d', 'More than 50, with written findings', 100],
                ], 'required', 'Interview notes, a feedback summary or a survey export.', '', $unknown('Count the feedback conversations you have had so far and keep a list.')),
                $q('customer_commitment', 'What is the strongest commitment a customer has made?', [
                    ['a', 'Interest only (“sounds good”)', 10, 'Ask for a small commitment: a pre-order, a deposit, a letter of intent or a paid pilot.'],
                    ['b', 'Signed up for a free pilot or waiting list', 40, 'Convert pilot users into paying customers, even at a low price.'],
                    ['c', 'Paid, pre-ordered or signed a letter of intent', 80],
                    ['d', 'Paid repeatedly or renewed', 100],
                ], 'optional', 'Receipt, contract, letter of intent or sign-up list (no personal customer data needed).', '', $unknown('Review what each pilot user or customer has actually agreed to.')),
            ]],
        ['key' => 'market_understanding', 'title' => 'Market Understanding', 'weight' => 10,
            'description' => 'Who you sell to first, how big that market is, and who you compete with.',
            'anchors' => $anchors('“Everyone” is the customer; no view of competitors.', 'A first customer group is defined; size is a rough guess.', 'A sourced, bottom-up market estimate and a clear view of alternatives.'),
            'questions' => [
                $q('target_segment', 'Have you defined a specific first customer group?', [
                    ['a', 'No, we serve everyone', 0, 'Pick one narrow group you can reach first and describe them in two sentences.'],
                    ['b', 'Roughly', 45, 'Describe the group precisely: who, where, how many, and how they buy today.'],
                    ['c', 'Yes, clearly described', 100],
                ], 'none', '', '', null),
                $q('market_size', 'Do you know how many customers you could realistically reach in 3 years?', [
                    ['a', 'No estimate', 0, 'Estimate from the bottom up (by district, channel or partner) how many customers you can reach in 3 years.'],
                    ['b', 'Rough guess', 40, 'Back your estimate with sources (surveys, government data, partners).'],
                    ['c', 'Estimate with sources', 100],
                ], 'optional', 'The sources you used (report name, dataset, partner figures).', '', $unknown('Find one public source on the size of your first customer group.')),
                $q('competition', 'Do you know the main alternatives customers use today?', [
                    ['a', 'We have no competitors', 10, 'List the three main things customers do today instead (including doing nothing) and their prices.'],
                    ['b', 'We know a few, informally', 50, 'Write a short comparison: price, strengths and weaknesses of each alternative.'],
                    ['c', 'Yes, with a written comparison', 100],
                ], 'optional', 'Your comparison table or notes.', '', $unknown('Ask five customers what they use today instead of your product.')),
            ]],
        ['key' => 'product_readiness', 'title' => 'Product Readiness', 'weight' => 10,
            'description' => 'How far the product or service has developed and how well it works for users.',
            'anchors' => $anchors('An idea or sketch only.', 'A working prototype or pilot with real users.', 'A launched product improved from user feedback, with quality under control.'),
            'questions' => [
                $q('product_stage', 'How far along is your product or service?', [
                    ['a', 'An idea', 0, 'Build the simplest version a customer can try within weeks (a mock-up, pilot or manual service).'],
                    ['b', 'Prototype or pilot', 40, 'Run the pilot with real users and measure whether it solves their problem.'],
                    ['c', 'Launched to paying or regular users', 80],
                    ['d', 'Launched and improved from user feedback several times', 100],
                ], 'optional', 'A link, photos, demo video or pilot report.', '', null),
                $q('product_feedback', 'How do you collect and act on user feedback?', [
                    ['a', 'We do not collect it', 0, 'Set up one simple feedback channel (calls, a form or WhatsApp group) and review it weekly.'],
                    ['b', 'Informally, when users complain', 35, 'Log every piece of feedback and decide monthly what to change.'],
                    ['c', 'Regularly, and we have made changes because of it', 85],
                    ['d', 'Systematically, with measured results of changes', 100],
                ], 'optional', 'Your feedback log or a list of changes made.', '', $unknown('Ask the team how feedback reaches you today and write it down.')),
            ]],
        ['key' => 'business_model_strength', 'title' => 'Business Model Strength', 'weight' => 10,
            'description' => 'How the startup makes money and whether each sale is profitable.',
            'anchors' => $anchors('No clear way to make money.', 'A pricing model exists; unit profit is unclear or negative.', 'Positive, improving unit economics and a tested pricing model.'),
            'questions' => [
                $q('revenue_model', 'How clear is how you make money (who pays, for what, how often)?', [
                    ['a', 'Not decided', 0, 'Decide who pays, for what and how often; write it on one page and test the price with 5 customers.'],
                    ['b', 'Decided but not tested', 40, 'Test the price with real customers and record how many accept it.'],
                    ['c', 'Tested with paying customers', 85],
                    ['d', 'Tested and refined over several price changes', 100],
                ], 'none', '', '', null),
                $q('unit_economics', 'Do you know the profit you make on each sale after direct costs?', [
                    ['a', 'No', 0, 'Calculate price minus direct costs for one sale. If it is negative, fix pricing or costs first.'],
                    ['b', 'Roughly, and it is negative', 25, 'Find which cost to cut or how to raise the price so each sale makes money.'],
                    ['c', 'Yes, and it is positive', 85],
                    ['d', 'Yes, positive and improving', 100],
                ], 'optional', 'Your cost-per-sale calculation (a spreadsheet name is enough).', '', $unknown('List the direct costs of one sale and compare them with the price.')),
            ]],
        ['key' => 'market_traction', 'title' => 'Market Traction', 'weight' => 10,
            'description' => 'Evidence that the market is adopting what you offer: customers, revenue and retention.',
            'anchors' => $anchors('No customers or users yet.', 'First customers and some revenue; growth is uneven.', 'Steady growth in customers and revenue, and most customers stay.'),
            'questions' => [
                $q('paying_customers', 'How many paying customers or regular users do you have?', [
                    ['a', 'None', 0, 'Get your first paying customer, even at a low price. It is the strongest proof you can have.'],
                    ['b', '1 to 10', 35, 'Ask your first customers why they bought and use it to find the next 20.'],
                    ['c', '11 to 100', 70],
                    ['d', 'More than 100', 100],
                ], 'required', 'Sales records, invoices count or usage statistics (totals only, no customer names needed).', '', null),
                $q('revenue_trend', 'How has revenue changed over the last 6 months?', [
                    ['a', 'No revenue yet', 0, 'Set a first revenue target for the next 3 months and the steps to reach it.'],
                    ['b', 'Revenue, but flat or falling', 35, 'Find out why: talk to customers who stopped buying.'],
                    ['c', 'Growing slowly', 70],
                    ['d', 'Growing steadily month on month', 100],
                ], 'optional', 'Monthly revenue totals for the last 6 months.', '', $unknown('Put monthly sales totals for the last 6 months in one table.')),
                $q('retention', 'Do customers come back or keep using the product?', [
                    ['a', 'Most do not come back', 25, 'Interview customers who left and fix the main reason.'],
                    ['b', 'About half come back', 65],
                    ['c', 'Most come back', 100],
                ], 'optional', 'Repeat-purchase or active-user figures.', '', $unknown('Start tracking how many customers buy again or are still active after 3 months.')),
            ]],
        ['key' => 'team_capacity', 'title' => 'Team Capacity', 'weight' => 10,
            'description' => 'Whether the team has the time, skills and agreements to deliver the next stage.',
            'anchors' => $anchors('Part-time effort, unclear roles, key skills missing.', 'A committed core team with known gaps.', 'Full-time founders, skills covered, roles and ownership agreed in writing.'),
            'questions' => [
                $q('team_commitment', 'How much time do the founders give the startup?', [
                    ['a', 'Nobody works on it regularly yet', 0, 'Agree who will commit how many hours a week and write it down.'],
                    ['b', 'Founders work on it part-time', 40, 'Plan when at least one founder can work on it full-time and what has to happen first.'],
                    ['c', 'At least one founder is full-time', 75],
                    ['d', 'All key founders are full-time', 100],
                ], 'none', '', '', null),
                $q('team_skills', 'Does the team have the skills it needs for the next 12 months?', [
                    ['a', 'Several critical skills are missing', 30, 'For each missing skill, decide: hire, partner, mentor or train, and by when.'],
                    ['b', 'One or two gaps, with a plan to fill them', 70],
                    ['c', 'All critical skills are covered', 100],
                ], 'none', '', '', $unknown('List the 5 skills most critical for the next year and who covers each.')),
                $q('team_agreement', 'Is there a written founders’ agreement (roles, shares, decisions)?', [
                    ['a', 'No', 0, 'Write a short founders’ agreement covering roles, ownership, vesting and how decisions are made.'],
                    ['b', 'Discussed but not written', 35, 'Turn what you agreed into a signed document; ask Hive for a template.'],
                    ['c', 'Written and signed', 100],
                ], 'optional', 'The document name and date signed (do not upload the agreement here).', '', null),
            ]],
        ['key' => 'financial_readiness', 'title' => 'Financial Readiness', 'weight' => 10,
            'description' => 'How well money is recorded, planned and managed, and how long the startup can operate.',
            'anchors' => $anchors('No records; personal and business money mixed.', 'Basic monthly records and a rough cash view.', 'Monthly accounts, a budget that is followed and a known runway.'),
            'questions' => [
                $q('bookkeeping', 'How are your finances recorded?', [
                    ['a', 'Not recorded', 0, 'Start a simple monthly record of all money in and out, kept separate from personal money.'],
                    ['b', 'Some records, mixed with personal money', 25, 'Open a business account and record every transaction monthly.'],
                    ['c', 'Monthly records, business account', 75],
                    ['d', 'Monthly accounts and a budget we follow', 100],
                ], 'required', 'The name of your bookkeeping tool or the latest monthly report (no bank details).', '', null),
                $q('runway', 'How many months can you operate with the money you have?', [
                    ['a', 'Less than 3 months, no plan', 0, 'Make a 6-month cash plan and decide what to cut or what funding to seek now.'],
                    ['b', '3 to 6 months', 45, 'Start fundraising or revenue plans at least 6 months before cash runs out.'],
                    ['c', '6 to 12 months', 80],
                    ['d', 'More than 12 months, or profitable', 100],
                ], 'optional', 'Your cash plan or runway calculation.', '', $unknown('Add up cash in hand and average monthly spending to estimate your runway.')),
            ]],
        ['key' => 'scalability', 'title' => 'Scalability', 'weight' => 10,
            'description' => 'Whether the business can grow without costs and effort growing at the same rate.',
            'anchors' => $anchors('Every new customer needs the founders’ personal effort.', 'Some processes are repeatable; growth would strain the team.', 'Documented, repeatable processes and channels that can grow with demand.'),
            'questions' => [
                $q('repeatable_sales', 'Do you have a repeatable way to get new customers?', [
                    ['a', 'Not yet', 0, 'Try two or three channels in small tests and track the cost and result of each.'],
                    ['b', 'We are testing channels', 45, 'Keep the channel with the best cost per customer and write down how it works.'],
                    ['c', 'One channel works repeatedly', 80],
                    ['d', 'Several channels work, with known cost per customer', 100],
                ], 'optional', 'Channel results (customers and cost per channel).', '', $unknown('Record where each of your last 10 customers came from.')),
                $q('processes', 'Are key processes written down (sales, delivery, hiring)?', [
                    ['a', 'No', 0, 'Write a one-page checklist for the process you repeat most.'],
                    ['b', 'A few', 50, 'Document the remaining core processes so a new team member can follow them.'],
                    ['c', 'Most core processes', 100],
                ], 'none', '', '', null),
                $q('capacity_growth', 'If demand doubled next quarter, could you deliver?', [
                    ['a', 'No, we would fail to deliver', 10, 'Identify the first bottleneck (people, stock, systems) and plan how to remove it.'],
                    ['b', 'Only with great strain', 45, 'Plan what you would need (people, suppliers, tools) and how long it takes to add.'],
                    ['c', 'Yes, with a plan we have prepared', 100],
                ], 'none', '', '', $unknown('Walk through what would break first if orders doubled.')),
            ]],
        ['key' => 'commercialization_readiness', 'title' => 'Commercialization Readiness', 'weight' => 10,
            'description' => 'Whether the startup is ready to sell at scale: registration, compliance, protection and route to market.',
            'anchors' => $anchors('Not registered; obligations and route to market unknown.', 'Registered, with some compliance gaps and a route to market being tested.', 'Registered and compliant, with protected assets and a proven route to market.'),
            'questions' => [
                $q('registration', 'Is the business formally registered?', [
                    ['a', 'Not registered', 0, 'Register the business (URSB in Uganda) and keep the certificate on file.'],
                    ['b', 'Registration in progress', 50, 'Complete the registration and update your Hive profile with the number.'],
                    ['c', 'Registered', 100],
                ], 'optional', 'Registration number (also on your Hive profile).', '', null),
                $q('compliance', 'Are tax, statutory and sector obligations (licences, standards) up to date?', [
                    ['a', 'Several obligations are not met', 15, 'List the missing filings, licences or certifications and a date to complete each.'],
                    ['b', 'Partly up to date', 45, 'Complete the remaining obligations before you sell at larger scale.'],
                    ['c', 'Fully up to date', 100],
                ], 'optional', 'Names of licences or certificates held (no copies needed here).', '', $unknown('Find out which taxes, filings and licences apply (for example URA TIN, NSSF, UNBS) and set reminders.')),
                $q('route_to_market', 'Do you have agreements to reach customers at scale (distributors, partners, contracts)?', [
                    ['a', 'None yet', 0, 'Identify two partners or distributors who already reach your customers and start conversations.'],
                    ['b', 'In discussion', 40, 'Turn the most promising discussion into a written pilot agreement.'],
                    ['c', 'At least one signed agreement', 80],
                    ['d', 'Several active agreements delivering sales', 100],
                ], 'optional', 'Partner names or agreement dates (no contract copies needed here).', '', $unknown('List the partners you have spoken to and where each conversation stands.')),
            ]],
    ],
    // PROVISIONAL stage cut-offs (not owner-approved). Labels match the earlier starter rubric.
    'bands' => [
        ['min' => 0, 'max' => 24.9, 'label' => 'Idea', 'description' => 'Exploring the problem and the team.'],
        ['min' => 25, 'max' => 44.9, 'label' => 'Validation', 'description' => 'Testing that customers have the problem and want the solution.'],
        ['min' => 45, 'max' => 64.9, 'label' => 'Early traction', 'description' => 'First customers and revenue; building repeatable sales.'],
        ['min' => 65, 'max' => 84.9, 'label' => 'Growth', 'description' => 'Repeatable sales and solid basics; ready to scale carefully.'],
        ['min' => 85, 'max' => 100, 'label' => 'Scale', 'description' => 'Strong across all areas; ready for larger investment conversations.'],
    ],
    // PROVISIONAL insufficient-information and gap rules.
    'scoring' => ['min_answered_share' => 0.5, 'min_scored_weight_share' => 0.7, 'gap_below' => 50, 'strength_from' => 70],
];
