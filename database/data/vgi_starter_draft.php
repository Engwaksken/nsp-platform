<?php

/*
| Starter VGI rubric: a PROPOSAL for Hive to review, edit and approve.
| It is seeded as a draft and is never shown to founders until an
| administrator approves it in Admin › Growth assessments.
| Scores: 0 = not started, 25 = early, 50 = in progress, 75 = solid, 100 = strong.
*/

$q = fn (string $key, string $prompt, array $options, string $evidence = 'none', string $help = '') => [
    'key' => $key, 'prompt' => $prompt, 'help' => $help, 'weight' => 1, 'evidence' => $evidence,
    'options' => array_map(fn ($o) => ['key' => $o[0], 'label' => $o[1], 'score' => $o[2], 'tip' => $o[3] ?? null], $options),
];

return [
    'dimensions' => [
        ['key' => 'team', 'title' => 'Team', 'description' => 'The people building the startup and how they work together.', 'weight' => 15, 'questions' => [
            $q('team_commitment', 'How much time do the founders give the startup?', [
                ['a', 'Nobody works on it regularly yet', 0, 'Agree who will commit how many hours a week and write it down.'],
                ['b', 'Founders work on it part-time', 40, 'Plan when at least one founder can work on it full-time and what has to happen first.'],
                ['c', 'At least one founder is full-time', 75],
                ['d', 'All key founders are full-time', 100],
            ]),
            $q('team_skills', 'Does the team have the skills it needs for the next 12 months?', [
                ['a', 'We have not listed the skills we need', 0, 'List the 5 skills most critical for the next year and who covers each.'],
                ['b', 'Several critical skills are missing', 30, 'For each missing skill, decide: hire, partner, mentor or train, and by when.'],
                ['c', 'One or two gaps, with a plan to fill them', 70],
                ['d', 'All critical skills are covered', 100],
            ]),
            $q('team_agreement', 'Is there a written founders’ agreement (roles, shares, decisions)?', [
                ['a', 'No', 0, 'Write a short founders’ agreement covering roles, ownership, vesting and how decisions are made.'],
                ['b', 'Discussed but not written', 35, 'Turn what you agreed into a signed document; ask Hive for a template.'],
                ['c', 'Written and signed', 100],
            ], 'optional'),
        ]],
        ['key' => 'problem_solution', 'title' => 'Problem and solution', 'description' => 'How well you understand the problem and how well your product solves it.', 'weight' => 15, 'questions' => [
            $q('problem_validation', 'How many potential customers have you talked to about the problem?', [
                ['a', 'None yet', 0, 'Interview at least 15 potential customers about the problem before building more.'],
                ['b', 'Fewer than 10', 30, 'Reach 20 or more interviews and note the patterns you hear.'],
                ['c', '10 to 50, with notes', 75],
                ['d', 'More than 50, with written findings', 100],
            ], 'optional'),
            $q('solution_stage', 'How far along is your product or service?', [
                ['a', 'An idea', 0, 'Build the simplest version a customer can try within weeks (a mock-up, pilot or manual service).'],
                ['b', 'Prototype or pilot', 40, 'Run the pilot with real users and measure whether it solves their problem.'],
                ['c', 'Launched to paying or regular users', 80],
                ['d', 'Launched and improved from user feedback several times', 100],
            ]),
            $q('differentiation', 'Can you explain why customers would choose you over alternatives?', [
                ['a', 'Not clearly', 10, 'List the 3 main alternatives customers use today and write one sentence on why yours is better for them.'],
                ['b', 'Yes, but not tested with customers', 50, 'Test your reason with customers: do they agree it matters?'],
                ['c', 'Yes, and customers confirm it', 100],
            ]),
        ]],
        ['key' => 'market', 'title' => 'Market and customers', 'description' => 'Who you sell to and how you reach them.', 'weight' => 15, 'questions' => [
            $q('target_customer', 'Have you defined a specific first customer group?', [
                ['a', 'No, we serve everyone', 0, 'Pick one narrow group you can reach first and describe them in two sentences.'],
                ['b', 'Roughly', 45, 'Describe the group precisely: who, where, how many, and how they buy today.'],
                ['c', 'Yes, clearly described', 100],
            ]),
            $q('market_size', 'Do you know how many customers you could realistically reach in 3 years?', [
                ['a', 'No', 0, 'Estimate how many customers you can reach in 3 years from the bottom up (by district, channel or partner).'],
                ['b', 'Rough guess', 40, 'Back your estimate with sources (surveys, government data, partners).'],
                ['c', 'Estimate with sources', 100],
            ], 'optional'),
            $q('channels', 'Do you have a repeatable way to get new customers?', [
                ['a', 'Not yet', 0, 'Try two or three channels in small tests and track the cost and result of each.'],
                ['b', 'We are testing channels', 45, 'Keep the channel with the best cost per customer and write down how it works.'],
                ['c', 'One channel works repeatedly', 80],
                ['d', 'Several channels work, with known cost per customer', 100],
            ]),
        ]],
        ['key' => 'traction', 'title' => 'Traction and revenue', 'description' => 'Evidence that customers want what you offer.', 'weight' => 20, 'questions' => [
            $q('customers', 'How many paying customers or regular users do you have?', [
                ['a', 'None', 0, 'Get your first paying customer, even at a low price. It is the strongest proof you can have.'],
                ['b', '1 to 10', 35, 'Ask your first customers why they bought and use it to find the next 20.'],
                ['c', '11 to 100', 70],
                ['d', 'More than 100', 100],
            ], 'optional'),
            $q('revenue_trend', 'How has revenue changed over the last 6 months?', [
                ['a', 'No revenue yet', 0, 'Set a first revenue target for the next 3 months and the steps to reach it.'],
                ['b', 'Revenue, but flat or falling', 35, 'Find out why: talk to customers who stopped buying.'],
                ['c', 'Growing slowly', 70],
                ['d', 'Growing steadily month on month', 100],
            ]),
            $q('retention', 'Do customers come back or keep using the product?', [
                ['a', 'We do not track this', 0, 'Start tracking how many customers buy again or are still active after 3 months.'],
                ['b', 'Most do not come back', 25, 'Interview customers who left and fix the main reason.'],
                ['c', 'About half come back', 65],
                ['d', 'Most come back', 100],
            ]),
        ]],
        ['key' => 'business_finance', 'title' => 'Business model and finance', 'description' => 'How the startup makes money and manages it.', 'weight' => 20, 'questions' => [
            $q('unit_economics', 'Do you know the profit you make on each sale after direct costs?', [
                ['a', 'No', 0, 'Calculate price minus direct costs for one sale. If it is negative, fix pricing or costs first.'],
                ['b', 'Roughly, and it is negative', 25, 'Find which cost to cut or how to raise the price so each sale makes money.'],
                ['c', 'Yes, and it is positive', 85],
                ['d', 'Yes, positive and improving', 100],
            ]),
            $q('bookkeeping', 'How are your finances recorded?', [
                ['a', 'Not recorded', 0, 'Start a simple monthly record of all money in and out, kept separate from personal money.'],
                ['b', 'Some records, mixed with personal money', 25, 'Open a business account and record every transaction monthly.'],
                ['c', 'Monthly records, business account', 75],
                ['d', 'Monthly accounts and a budget we follow', 100],
            ], 'optional'),
            $q('runway', 'How many months can you operate with the money you have?', [
                ['a', 'Less than 3 months, no plan', 0, 'Make a 6-month cash plan and decide what to cut or what funding to seek now.'],
                ['b', '3 to 6 months', 45, 'Start fundraising or revenue plans at least 6 months before cash runs out.'],
                ['c', '6 to 12 months', 80],
                ['d', 'More than 12 months, or profitable', 100],
            ]),
        ]],
        ['key' => 'operations', 'title' => 'Operations and compliance', 'description' => 'Registration, governance and the basics of running a company.', 'weight' => 15, 'questions' => [
            $q('registration', 'Is the business formally registered?', [
                ['a', 'Not registered', 0, 'Register the business (URSB in Uganda) and keep the certificate on file.'],
                ['b', 'Registration in progress', 50, 'Complete the registration and update your Hive profile with the number.'],
                ['c', 'Registered', 100],
            ], 'optional'),
            $q('tax_compliance', 'Are tax and statutory obligations up to date?', [
                ['a', 'We do not know our obligations', 0, 'Find out which taxes and filings apply (for example URA TIN, NSSF) and set reminders.'],
                ['b', 'Partly up to date', 40, 'List the missing filings and a date to complete each.'],
                ['c', 'Fully up to date', 100],
            ]),
            $q('processes', 'Are key processes written down (sales, delivery, hiring)?', [
                ['a', 'No', 0, 'Write a one-page checklist for the process you repeat most.'],
                ['b', 'A few', 50, 'Document the remaining core processes so a new team member can follow them.'],
                ['c', 'Most core processes', 100],
            ]),
        ]],
    ],
    'bands' => [
        ['min' => 0, 'max' => 24.9, 'label' => 'Idea', 'description' => 'Exploring the problem and the team.'],
        ['min' => 25, 'max' => 44.9, 'label' => 'Validation', 'description' => 'Testing that customers have the problem and want the solution.'],
        ['min' => 45, 'max' => 64.9, 'label' => 'Early traction', 'description' => 'First customers and revenue; building repeatable sales.'],
        ['min' => 65, 'max' => 84.9, 'label' => 'Growth', 'description' => 'Repeatable sales and solid basics; ready to scale carefully.'],
        ['min' => 85, 'max' => 100, 'label' => 'Scale', 'description' => 'Strong across all areas; ready for larger investment.'],
    ],
];
