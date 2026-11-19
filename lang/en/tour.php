<?php

/*
 * The "?" page guide. Each page's tour lives in resources/js/tours/tours.json
 * and points at these keys; add a group per page below.
 */
return [
    'ui' => [
        'button' => 'Page guide',
        'next' => 'Next',
        'previous' => 'Back',
        'done' => 'Done',
        'progress' => '{{current}} of {{total}}',
    ],

    'common' => [
        'sidebar' => [
            'title' => 'The side menu',
            'body' => 'Everything you can open is here, grouped by topic. You only see the areas your role allows. Use the arrow at the top to collapse it.',
        ],
        'switcher' => [
            'title' => 'Switch workspace',
            'body' => 'If you belong to several teams or departments, pick the one you want to work in here. The menu below changes with it.',
        ],
        'user_menu' => [
            'title' => 'Your account',
            'body' => 'Open your profile, security settings, appearance and language, your earnings, or sign out.',
        ],
        'guide' => [
            'title' => 'This guide, on every page',
            'body' => 'Press the ? button at the top of any page to get a tour of what that page does.',
        ],
    ],

    'dashboard' => [
        'intro' => [
            'title' => 'The organisation dashboard',
            'body' => 'A working overview of the whole platform: what waits for someone, how events are filling up, where the money stands and how the community is growing.',
        ],
        'attention' => [
            'title' => 'Needs your attention',
            'body' => 'Each card counts something waiting for a decision: registrations, new teams, funding, approvals, suggestions and messages. Click a card to open that queue. A green check means it is clear.',
        ],
        'events' => [
            'title' => 'Upcoming events',
            'body' => 'The next events with days to go, days until registration closes and a bar of approved and pending teams against capacity.',
        ],
        'registrations' => [
            'title' => 'Registrations, last 14 days',
            'body' => 'New event registrations per day. Hover a bar for the exact number; the total and how many came from the website form are above it.',
        ],
        'money' => [
            'title' => 'Money',
            'body' => 'Income and spending posted this month, what team and project budgets hold, and the balance of each fund.',
        ],
        'community' => [
            'title' => 'Community',
            'body' => 'Active and pending teams, team members, new teams this month, robots and published projects.',
        ],
        'activity' => [
            'title' => 'Recent activity',
            'body' => 'The latest actions across the platform and who did them. The full history is in the audit log.',
        ],
    ],

    'team_dashboard' => [
        'intro' => [
            'title' => 'Your team dashboard',
            'body' => 'A quick look at your team: members, projects, robots, what your parts cost and the events you have entered.',
        ],
        'tiles' => [
            'title' => 'Team at a glance',
            'body' => 'Members against your tier limit, published projects and drafts, active robots and the total cost of their parts lists.',
        ],
        'events' => [
            'title' => 'Upcoming events',
            'body' => 'Events your team has entered or been invited to, with the status of each entry. Click one to open it.',
        ],
    ],

    'departments' => [
        'intro' => [
            'title' => 'Departments',
            'body' => 'Departments are the foundation\'s working groups, such as Events, Finance and Media. Each one works only in the areas you allow it.',
        ],
        'search' => [
            'title' => 'Find a department',
            'body' => 'Type a name to filter the list.',
        ],
        'new' => [
            'title' => 'New department',
            'body' => 'Create a department with its English and Arabic names. Then set the areas it may work in and appoint its admin.',
        ],
        'table' => [
            'title' => 'All departments',
            'body' => 'Open a department to manage its members, the permissions it may delegate and its report.',
        ],
    ],

    'department' => [
        'intro' => [
            'title' => 'A department\'s page',
            'body' => 'Everything about one department: its members, what each may do and its recent activity. Department admins invite people and delegate permissions.',
        ],
        'tabs' => [
            'title' => 'Tabs',
            'body' => 'Overview shows your access and recent activity. Members lets admins invite and manage people. Permissions sets what the department may do and who may do what. Settings edits its names.',
        ],
        'overview' => [
            'title' => 'Overview',
            'body' => 'Member counts and your own access in this department, then the latest actions taken here.',
        ],
        'report' => [
            'title' => 'Department report',
            'body' => 'Opens this department\'s figures and an exportable report. You see it only if you may view reports.',
        ],
    ],

    'teams_list' => [
        'intro' => [
            'title' => 'Teams',
            'body' => 'Every team on the platform with its status, tier and supervising department. Teams registered from the website arrive as pending until you approve them.',
        ],
        'search' => [
            'title' => 'Search teams',
            'body' => 'Type part of a team name to filter the list.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow the list by status (pending, active, suspended), tier or supervising department.',
        ],
        'new' => [
            'title' => 'New team',
            'body' => 'Create a team and, if you like, invite its captain right away. Only people who may edit teams see this button.',
        ],
        'table' => [
            'title' => 'Team list',
            'body' => 'Click a team to open it: members, tier, features, status and history. Click a column heading to sort.',
        ],
    ],

    'team_show' => [
        'intro' => [
            'title' => 'A team\'s page',
            'body' => 'Manage one team: who is in it, what its tier allows and whether it is active. Approving a pending team happens here.',
        ],
        'tabs' => [
            'title' => 'Tabs',
            'body' => 'Overview has the details and history. Members lists the team and its captain. Features shows what the tier allows and lets you override it for this team. Settings edits the team.',
        ],
        'overview' => [
            'title' => 'Overview',
            'body' => 'The team\'s details, its tier and supervising department, and its recent activity.',
        ],
        'status' => [
            'title' => 'Change status',
            'body' => 'Approve a pending team, suspend an active one or reactivate it. Suspending needs a reason, which is saved with the team.',
        ],
    ],

    'tiers' => [
        'intro' => [
            'title' => 'Tiers',
            'body' => 'A tier is a package of features. Every team is on one tier, and changing a tier changes all its teams straight away.',
        ],
        'new' => [
            'title' => 'New tier',
            'body' => 'Create a tier and choose its features and limits, such as how many members a team may have.',
        ],
        'list' => [
            'title' => 'The tiers',
            'body' => 'Each card lists the tier\'s features. Edit it, make it the default for new teams, or delete it when no team uses it.',
        ],
    ],

    'users' => [
        'intro' => [
            'title' => 'Users',
            'body' => 'Everyone with an account. Deactivating someone stops them signing in without deleting anything they did.',
        ],
        'search' => [
            'title' => 'Find a person',
            'body' => 'Search by name or email.',
        ],
        'filters' => [
            'title' => 'Active or inactive',
            'body' => 'Show everyone, only active accounts, or only deactivated ones.',
        ],
        'table' => [
            'title' => 'The people',
            'body' => 'Use the row actions to deactivate or reactivate an account, or to sign in as that person for support. Signing in as someone is logged and cannot be used on super administrators.',
        ],
    ],

    'events_list' => [
        'intro' => [
            'title' => 'Events',
            'body' => 'All the foundation\'s events, from draft to archived. Each belongs to a department and has categories, a registration window and results.',
        ],
        'search' => [
            'title' => 'Search events',
            'body' => 'Type part of an event title.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by status (draft, open, closed, running, finished, archived) or by the department that organises the event.',
        ],
        'new' => [
            'title' => 'New event',
            'body' => 'Start a draft event. Add its categories and dates, then open it for registration. Only people who may edit events see this button.',
        ],
        'table' => [
            'title' => 'The events',
            'body' => 'Click an event to manage it: entries, invitations, results, cover and files.',
        ],
    ],

    'event_show' => [
        'intro' => [
            'title' => 'An event\'s page',
            'body' => 'Run one event from here: its categories, who entered, invitations, results, cover photo and downloadable files.',
        ],
        'lifecycle' => [
            'title' => 'Move the event forward',
            'body' => 'These buttons move the event through its stages: open registration, close it, mark it running, finished and archived. An event needs at least one category before it can open.',
        ],
        'tabs' => [
            'title' => 'Tabs',
            'body' => 'Categories sets ages, team sizes and robot limits. Entries is where you approve, waitlist or reject teams, including those registered on the website. Results records placements. Cover & files holds the photo and downloads.',
        ],
        'overview' => [
            'title' => 'Overview',
            'body' => 'Dates, venue and visibility, how many teams are approved against the capacity, and the registration window.',
        ],
    ],

    'parts' => [
        'intro' => [
            'title' => 'Parts catalogue',
            'body' => 'The shared list of robot parts that teams browse and use in their parts lists. Approved parts are visible to every team.',
        ],
        'nav' => [
            'title' => 'Catalogue sections',
            'body' => 'Switch between parts, categories, vendors and the suggestions teams have sent for your review.',
        ],
        'search' => [
            'title' => 'Search parts',
            'body' => 'Search by part name or part number.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by status (approved, suggested, rejected, archived) or by category.',
        ],
        'new' => [
            'title' => 'New part',
            'body' => 'Add a part with its category and specifications, then add vendor prices on its page. Only people who may manage the catalogue see this button.',
        ],
        'table' => [
            'title' => 'The parts',
            'body' => 'Click a part to edit it and manage its vendor offers and prices.',
        ],
    ],

    'part_show' => [
        'intro' => [
            'title' => 'A part\'s page',
            'body' => 'Edit one part and the prices vendors offer for it. Teams see the cheapest offers when they price their robots.',
        ],
        'nav' => [
            'title' => 'Catalogue sections',
            'body' => 'Go back to the parts list or to categories, vendors and suggestions.',
        ],
        'details' => [
            'title' => 'Part details',
            'body' => 'Name, part number, category, descriptions in both languages and technical specifications.',
        ],
        'offers' => [
            'title' => 'Vendor offers',
            'body' => 'Add one price per vendor with a link and whether it is in stock. Save the offers when you are done; teams see them straight away.',
        ],
    ],

    'categories' => [
        'intro' => [
            'title' => 'Part categories',
            'body' => 'Categories group the parts so teams can find them. They go two levels deep: a category and its sub-categories.',
        ],
        'nav' => [
            'title' => 'Catalogue sections',
            'body' => 'Switch between parts, categories, vendors and suggestions.',
        ],
        'new' => [
            'title' => 'New category',
            'body' => 'Add a top-level category, or add a sub-category from an existing one. Only people who may manage the catalogue see this.',
        ],
        'list' => [
            'title' => 'The categories',
            'body' => 'Rename a category, add a sub-category or remove one that has no parts.',
        ],
    ],

    'vendors' => [
        'intro' => [
            'title' => 'Vendors',
            'body' => 'The shops and suppliers that sell the parts, with their contact details and location.',
        ],
        'nav' => [
            'title' => 'Catalogue sections',
            'body' => 'Switch between parts, categories, vendors and suggestions.',
        ],
        'search' => [
            'title' => 'Search vendors',
            'body' => 'Search by vendor name.',
        ],
        'new' => [
            'title' => 'New vendor',
            'body' => 'Add a shop with its city, phone and website so teams know where to buy. Only people who may manage the catalogue see this button.',
        ],
        'table' => [
            'title' => 'The vendors',
            'body' => 'Edit a vendor, or archive one you no longer use; its past offers stay in teams\' parts lists.',
        ],
    ],

    'suggestions' => [
        'intro' => [
            'title' => 'Suggestions from teams',
            'body' => 'Teams suggest parts, vendors and prices they need. Only the suggesting team sees them until you decide.',
        ],
        'nav' => [
            'title' => 'Catalogue sections',
            'body' => 'The number beside Suggestions is how many are waiting.',
        ],
        'filters' => [
            'title' => 'Show one kind',
            'body' => 'Filter the queue to parts, vendors or prices, or show everything.',
        ],
        'list' => [
            'title' => 'The queue',
            'body' => 'Approve a suggestion to add it to the catalogue for every team (you can edit it first), merge it into an existing item if it is a duplicate, or reject it with a note the team will see.',
        ],
    ],

    'posts_list' => [
        'intro' => [
            'title' => 'News',
            'body' => 'The articles shown on the public website. Each is written in Arabic and English and moves from draft to published.',
        ],
        'search' => [
            'title' => 'Search articles',
            'body' => 'Search by title.',
        ],
        'filters' => [
            'title' => 'Filter by status',
            'body' => 'Draft, in review, scheduled, published or archived.',
        ],
        'new' => [
            'title' => 'New article',
            'body' => 'Start a draft with its department and titles, then write the text in the editor. Only people who may edit content see this button.',
        ],
        'table' => [
            'title' => 'The articles',
            'body' => 'Click an article to write, schedule or publish it. Only published articles appear on the website.',
        ],
    ],

    'post_edit' => [
        'intro' => [
            'title' => 'Writing an article',
            'body' => 'Write the title, summary and text in both languages, save, then publish. Nothing shows on the website until it is published.',
        ],
        'department' => [
            'title' => 'Department',
            'body' => 'The department that owns the article. It decides who may edit and publish it.',
        ],
        'arabic' => [
            'title' => 'Arabic text',
            'body' => 'The title, short summary and full text for Arabic readers. The summary appears on the news list and in link previews.',
        ],
        'english' => [
            'title' => 'English text',
            'body' => 'The same in English. If you leave it empty, English readers see the Arabic text.',
        ],
        'publishing' => [
            'title' => 'Publishing',
            'body' => 'Publish now, or pick a date and time to schedule it (Libya time). Authors without publishing rights send it for review instead.',
        ],
    ],

    'inbox_list' => [
        'intro' => [
            'title' => 'Messages',
            'body' => 'Messages sent through the website\'s contact form. Each is assigned, answered and closed here.',
        ],
        'search' => [
            'title' => 'Search messages',
            'body' => 'Search by name, email or subject.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Show new, in-progress or closed messages, or only those assigned to a particular person.',
        ],
        'table' => [
            'title' => 'The messages',
            'body' => 'Click a message to read it, add internal notes and reply by email.',
        ],
    ],

    'inbox_show' => [
        'intro' => [
            'title' => 'A message',
            'body' => 'Read what the visitor wrote, decide who handles it and keep track of the conversation.',
        ],
        'details' => [
            'title' => 'Sender, status and owner',
            'body' => 'Who wrote it, then the status (new, in progress, closed) and who it is assigned to. Change them here as you work on it.',
        ],
        'reply' => [
            'title' => 'Reply by email',
            'body' => 'Opens your email app with the visitor\'s address and subject filled in. The platform does not send the reply for you.',
        ],
        'notes' => [
            'title' => 'Internal notes',
            'body' => 'Notes only your colleagues can see, such as what you decided or who you called.',
        ],
    ],

    'settings' => [
        'intro' => [
            'title' => 'Platform settings',
            'body' => 'A few settings that apply to the whole platform. Only super administrators can change them; press Save at the bottom when you finish.',
        ],
        'inbox' => [
            'title' => 'Inbox',
            'body' => 'Which department receives the website\'s contact messages, and the address shown to visitors who want to write to you.',
        ],
        'events' => [
            'title' => 'Events',
            'body' => 'Whether a waiting-list team is promoted automatically when an approved team withdraws.',
        ],
        'retention' => [
            'title' => 'Data retention',
            'body' => 'How many months contact messages are kept before they are cleared.',
        ],
    ],

    'audit' => [
        'intro' => [
            'title' => 'Activity log',
            'body' => 'A record of who did what and when across the platform. It cannot be edited, so it is the place to look when something needs explaining.',
        ],
        'search' => [
            'title' => 'Search the log',
            'body' => 'Search the description of an action.',
        ],
        'filters' => [
            'title' => 'Narrow it down',
            'body' => 'Filter by area (such as finance or events), by the kind of record, by actions taken while signing in as someone else, and by date range.',
        ],
        'table' => [
            'title' => 'The entries',
            'body' => 'Each row shows the action, who did it (and who they were impersonated by, if anyone) and the details that changed.',
        ],
    ],

    'report' => [
        'intro' => [
            'title' => 'Department report',
            'body' => 'What happened in this department\'s areas over a period: counts for each area and what each member did.',
        ],
        'range' => [
            'title' => 'Choose the period',
            'body' => 'Pick a start and end date and press Show. The figures below change to match.',
        ],
        'tiles' => [
            'title' => 'The figures',
            'body' => 'One card per area the department works in, such as events handled or articles published.',
        ],
        'members' => [
            'title' => 'Activity by members',
            'body' => 'How many actions each member took in the period.',
        ],
        'export' => [
            'title' => 'Download CSV',
            'body' => 'Saves the report as a spreadsheet file. Only people who may export reports see this button.',
        ],
    ],
];
