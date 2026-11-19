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

    'fin_dashboard' => [
        'intro' => [
            'title' => 'Finance overview',
            'body' => 'The foundation\'s money at a glance. Every movement is recorded in a ledger that is never edited, only reversed, and nobody approves their own work.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'Accounts, transactions, sponsors, receipts, payouts, team budgets, approvals and fiscal periods. The number on Approvals is what waits for a decision.',
        ],
        'cards' => [
            'title' => 'What is waiting',
            'body' => 'Transactions submitted for approval, and drafts that were started but not yet submitted. Click a card to open the list.',
        ],
        'accounts' => [
            'title' => 'Foundation accounts',
            'body' => 'The funds the foundation holds, with their balances. Team and project budgets are listed under Accounts.',
        ],
        'latest' => [
            'title' => 'Latest posted movements',
            'body' => 'The most recent transactions that were approved and posted to the ledger.',
        ],
    ],

    'accounts' => [
        'intro' => [
            'title' => 'Accounts',
            'body' => 'Every account and its current balance: the foundation\'s funds, team and project budgets, and the outside parties money comes from or goes to.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'Move between accounts, transactions, sponsors, receipts, payouts, team budgets, approvals and periods.',
        ],
        'new' => [
            'title' => 'Open an account',
            'body' => 'Create a new fund or budget account. Only finance editors see this button.',
        ],
        'list' => [
            'title' => 'The accounts',
            'body' => 'Foundation accounts are ours; outside parties mirror sponsors, vendors and people. Click an account to see its statement.',
        ],
    ],

    'account_show' => [
        'intro' => [
            'title' => 'An account statement',
            'body' => 'Every posted movement in and out of this account, with a running balance.',
        ],
        'balance' => [
            'title' => 'Current balance',
            'body' => 'What the account holds now, from posted movements only.',
        ],
        'range' => [
            'title' => 'Choose the period',
            'body' => 'Pick dates and press Show to see the statement for that period.',
        ],
        'statement' => [
            'title' => 'The movements',
            'body' => 'Date, description, money in and out, and the balance after each movement.',
        ],
    ],

    'transactions' => [
        'intro' => [
            'title' => 'Transactions',
            'body' => 'Money moving between accounts. A person drafts it, submits it, and a different person approves it; once posted it can only be reversed.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'Approvals shows how many items wait for a decision.',
        ],
        'search' => [
            'title' => 'Search',
            'body' => 'Search descriptions and references.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by status (draft, submitted, posted, rejected) and by type.',
        ],
        'new' => [
            'title' => 'New transaction',
            'body' => 'Draft a movement between two accounts in the same currency, with a reason. It is saved as a draft until you submit it.',
        ],
        'table' => [
            'title' => 'The transactions',
            'body' => 'Open a row to submit a draft or, if you may approve, to approve, reject or reverse it. Approving asks for your password.',
        ],
    ],

    'approvals' => [
        'intro' => [
            'title' => 'Approvals',
            'body' => 'Everything others prepared and you may decide. You asked for your password because approving moves money; you can never approve your own work.',
        ],
        'payouts' => [
            'title' => 'Payouts',
            'body' => 'Payments to people. Approve or reject each one; large amounts need a second, different approver. Marking it paid is a separate step once the money is handed over.',
        ],
        'receipts' => [
            'title' => 'Receipts',
            'body' => 'Money received from sponsors and others, already split between accounts. Post it to book the money, or reject it with a reason.',
        ],
        'transactions' => [
            'title' => 'Transactions',
            'body' => 'Movements waiting for approval. Approve to post them to the ledger, or reject with a reason. A movement that would overdraw an account is refused.',
        ],
        'posted' => [
            'title' => 'Recently posted',
            'body' => 'What was approved lately, for reference.',
        ],
    ],

    'periods' => [
        'intro' => [
            'title' => 'Fiscal periods',
            'body' => 'The foundation books its money month by month. Each month is open until someone closes it.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'Periods is the last tab.',
        ],
        'list' => [
            'title' => 'The months',
            'body' => 'Open months accept new movements. Closed months are locked: nothing can be posted with a date inside them.',
        ],
        'close' => [
            'title' => 'Close a month',
            'body' => 'Closing is final, so do it only after the month is reconciled. Only people with approval rights see this button, and it asks for your password.',
        ],
    ],

    'sponsors' => [
        'intro' => [
            'title' => 'Sponsors',
            'body' => 'Companies, organisations and individuals who support the foundation, with their sponsorships and contacts.',
        ],
        'search' => [
            'title' => 'Search sponsors',
            'body' => 'Search by name in Arabic or English.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by type (company, organisation, individual, government) or by active or inactive.',
        ],
        'new' => [
            'title' => 'New sponsor',
            'body' => 'Add a sponsor, then its logo, contacts and sponsorships on its page. Only finance editors see this button.',
        ],
        'table' => [
            'title' => 'The sponsors',
            'body' => 'Click a sponsor to open it. Sponsors are deactivated, never deleted, so the history stays.',
        ],
    ],

    'sponsor_show' => [
        'intro' => [
            'title' => 'A sponsor',
            'body' => 'Everything about one sponsor: logo, sponsorships, contacts and details.',
        ],
        'logo' => [
            'title' => 'Logo',
            'body' => 'Upload the sponsor\'s logo (PNG, JPG or WebP). It shows on the public home page only while the sponsor is active and has agreed to be named.',
        ],
        'sponsorships' => [
            'title' => 'Sponsorships',
            'body' => 'Each agreement with this sponsor: what was pledged and how much has been received. Add one with its split rules.',
        ],
        'contacts' => [
            'title' => 'Contacts',
            'body' => 'The people at the sponsor to call or write to, with their role, phone and email. Their details are visible only to finance staff.',
        ],
        'details' => [
            'title' => 'Details',
            'body' => 'Names in both languages, type, website, notes, whether the sponsor may be named publicly, and whether it is active.',
        ],
    ],

    'sponsorship_show' => [
        'intro' => [
            'title' => 'A sponsorship',
            'body' => 'One agreement with a sponsor: how much was pledged, how much arrived, and how incoming money is split.',
        ],
        'figures' => [
            'title' => 'Pledged and received',
            'body' => 'The amount promised, the amount received so far and what is still outstanding.',
        ],
        'form' => [
            'title' => 'Terms and split rules',
            'body' => 'Title, kind, dates and the rules that split each receipt between accounts: a percentage or a fixed amount to each, with the remainder going to the general fund.',
        ],
        'receipts' => [
            'title' => 'Receipts',
            'body' => 'Payments received under this sponsorship. Record a new one from the Receipts tab.',
        ],
    ],

    'receipts' => [
        'intro' => [
            'title' => 'Receipts',
            'body' => 'Money the foundation received, from sponsors, donations, prizes and fees. Each receipt is split between accounts, then posted.',
        ],
        'search' => [
            'title' => 'Search receipts',
            'body' => 'Search by reference or payer.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by status (draft, submitted, posted, rejected) or by source.',
        ],
        'new' => [
            'title' => 'New receipt',
            'body' => 'Record money received: who paid, how much, when and how. Link it to a sponsorship so its split rules apply.',
        ],
        'table' => [
            'title' => 'The receipts',
            'body' => 'Open a receipt to review the proposed split, submit it, and see it through to posting.',
        ],
    ],

    'receipt_show' => [
        'intro' => [
            'title' => 'A receipt',
            'body' => 'Review how an incoming payment is split between accounts, then send it for approval.',
        ],
        'facts' => [
            'title' => 'The payment',
            'body' => 'Who paid, how much, when, how and which sponsorship it belongs to.',
        ],
        'split' => [
            'title' => 'Split between accounts',
            'body' => 'The platform proposes a split from the sponsorship rules. You may change it, but the lines must add up to the receipt exactly.',
        ],
        'submit' => [
            'title' => 'Submit for approval',
            'body' => 'Sends the receipt to someone who can approve it. Once they post it, the money is booked into the accounts.',
        ],
    ],

    'payouts' => [
        'intro' => [
            'title' => 'Payouts',
            'body' => 'Bonuses, stipends and other payments to people. Money leaves the ledger only when a payout is marked paid.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'Approved payouts are decided on the Approvals tab.',
        ],
        'filters' => [
            'title' => 'Filter the list',
            'body' => 'Narrow by status (draft, submitted, approved, paid, rejected, cancelled) or by type.',
        ],
        'new' => [
            'title' => 'New payout',
            'body' => 'Choose who is paid, the amount and the account it comes from. For someone under 18 you must name a guardian who receives the payment.',
        ],
        'table' => [
            'title' => 'The payouts',
            'body' => 'Submit a draft, then a different person approves it (two approvers for large amounts). After paying, mark it paid with the method and reference.',
        ],
    ],

    'team_budgets' => [
        'intro' => [
            'title' => 'Team budgets',
            'body' => 'Funding requests and expenses sent by the teams. Teams ask for money; once approved it sits in the team\'s budget, and they record what they spend with a receipt.',
        ],
        'nav' => [
            'title' => 'Finance sections',
            'body' => 'This is the Team budgets tab.',
        ],
        'review' => [
            'title' => 'Review and approve',
            'body' => 'Approving needs your password, so it happens on a protected view. Asking for changes and rejecting need no password.',
        ],
        'requests' => [
            'title' => 'Funding requests',
            'body' => 'Approve in full or in part with a note, ask the team to change the request, or reject it. You cannot approve a request you made.',
        ],
        'expenses' => [
            'title' => 'Expenses',
            'body' => 'Check each expense against its receipt, then approve it. The money leaves the team\'s budget; an expense larger than the budget is refused.',
        ],
    ],

    'members' => [
        'intro' => [
            'title' => 'Team members',
            'body' => 'Everyone on your team, with their role. The number shows how many places your tier allows.',
        ],
        'add' => [
            'title' => 'Add people',
            'body' => 'Invite someone by email so they can sign in, or add a member who has no login (for example a younger student). Only people allowed to manage the team see these buttons, and they are disabled when the team is full.',
        ],
        'list' => [
            'title' => 'The members',
            'body' => 'Each member shows their role and whether they can sign in. People who manage the team can change roles or remove members.',
        ],
        'leave' => [
            'title' => 'Leave the team',
            'body' => 'Everyone except the captain can leave the team from here.',
        ],
    ],

    'projects' => [
        'intro' => [
            'title' => 'Projects',
            'body' => 'What your team is building and writing about. Published projects can appear on the public website.',
        ],
        'new' => [
            'title' => 'New project',
            'body' => 'Start a project with an Arabic and an English title; you fill in the rest on its page. Only people who may create projects see this button.',
        ],
        'list' => [
            'title' => 'Your projects',
            'body' => 'Each card shows the status (draft or published), the number of robots and the publish date. Click one to edit it.',
        ],
    ],

    'project_edit' => [
        'intro' => [
            'title' => 'Edit a project',
            'body' => 'Write the project in Arabic and English. Nothing is public until it is published.',
        ],
        'arabic' => [
            'title' => 'Arabic text',
            'body' => 'Title, short summary and full text in Arabic. The editor supports headings, lists, links and images.',
        ],
        'english' => [
            'title' => 'English text',
            'body' => 'The same in English. Visitors see the language they chose, so fill both.',
        ],
        'save' => [
            'title' => 'Save',
            'body' => 'Saves your changes without publishing. If you may only view the project, this button is hidden.',
        ],
        'publishing' => [
            'title' => 'Publishing and robots',
            'body' => 'The captain or a coach publishes the project or moves it back to draft. Below are the robots linked to it, and you can delete the project.',
        ],
    ],

    'robots' => [
        'intro' => [
            'title' => 'Robots',
            'body' => 'Your team\'s robots. Each has a parts list priced from the catalogue, and robots entered in events need their measurements.',
        ],
        'new' => [
            'title' => 'New robot',
            'body' => 'Give the robot a name and a status, and optionally link it to a project. Only people who may create robots see this button.',
        ],
        'list' => [
            'title' => 'Your robots',
            'body' => 'Each card shows the status, how many parts are listed and the linked project. Click one to open its parts list and details.',
        ],
    ],

    'robot_show' => [
        'intro' => [
            'title' => 'A robot',
            'body' => 'The robot\'s parts list, its cost by vendor and its details, including the measurements events ask for.',
        ],
        'parts' => [
            'title' => 'Parts list',
            'body' => 'Add parts from the catalogue with a quantity. The total is the cheapest known price for each part, and parts without a price are counted separately.',
        ],
        'save_parts' => [
            'title' => 'Save the parts list',
            'body' => 'Your changes to the parts list are saved only when you press this.',
        ],
        'vendors' => [
            'title' => 'Shopping list by vendor',
            'body' => 'The same parts grouped by the vendor selling them cheapest, with the vendor\'s phone, so you can buy in one go. You can print this page.',
        ],
        'details' => [
            'title' => 'Details',
            'body' => 'Name, status, linked project, weight and the length, width and height in millimetres, and a description in Arabic and English. Event applications use the measurements.',
        ],
    ],

    'team_settings' => [
        'intro' => [
            'title' => 'Team settings',
            'body' => 'How your team appears on the website. The team name and tier are set by the organisers.',
        ],
        'logo' => [
            'title' => 'Team logo',
            'body' => 'Upload a PNG, JPG or WebP logo. It is shown on your public profile.',
        ],
        'basics' => [
            'title' => 'City and website',
            'body' => 'Where the team is based and its own website address, if it has one.',
        ],
        'texts' => [
            'title' => 'Descriptions',
            'body' => 'Say what the team works on, in Arabic and in English. Visitors see the language they chose.',
        ],
        'public' => [
            'title' => 'Show on the website',
            'body' => 'Tick this to publish your team profile. It is available only if your tier includes a public profile; then press Save.',
        ],
    ],

    'wevents' => [
        'intro' => [
            'title' => 'Events',
            'body' => 'Competitions and activities your team can join. Applications are made with your own members and robots.',
        ],
        'list' => [
            'title' => 'Open events',
            'body' => 'Each card shows the event, its dates and status. Click one to read the rules and apply.',
        ],
    ],

    'wevent_show' => [
        'intro' => [
            'title' => 'An event',
            'body' => 'Everything you need to decide and apply: the facts, the categories, the rules and your team\'s entries.',
        ],
        'facts' => [
            'title' => 'The facts',
            'body' => 'Name, status, dates and place of the event.',
        ],
        'categories' => [
            'title' => 'Categories',
            'body' => 'The competitions inside the event. You apply to a category, and each may limit the number of members, robots or teams.',
        ],
        'rules' => [
            'title' => 'Rules',
            'body' => 'Read these before applying. They are the rules the judges use.',
        ],
        'entries' => [
            'title' => 'Your entries',
            'body' => 'Your team\'s applications and their status. If you are invited you can accept; you can withdraw an entry while that is still allowed.',
        ],
        'apply' => [
            'title' => 'Apply',
            'body' => 'Choose the category, the members who take part and the robot. Warnings tell you about anything missing, such as robot measurements. Only people allowed to apply, and only while the event is open, see this form.',
        ],
    ],

    'budget' => [
        'intro' => [
            'title' => 'Budget',
            'body' => 'Your team asks the foundation for funding here, and records what it spends with the receipt.',
        ],
        'actions' => [
            'title' => 'Request or spend',
            'body' => 'Request funding to ask for money; once approved it sits in your team budget. Record an expense after you spend, with its receipt. Each button appears only for people allowed to use it.',
        ],
        'cards' => [
            'title' => 'Your budgets',
            'body' => 'The balance of each budget, with how much was funded and how much was spent. A budget is created when your first request is approved.',
        ],
        'requests' => [
            'title' => 'Funding requests',
            'body' => 'Your requests and where they stand: waiting, approved (maybe for less than you asked), sent back for changes, or rejected.',
        ],
        'expenses' => [
            'title' => 'Expenses',
            'body' => 'What you recorded and whether finance approved it. An approved expense leaves your budget; one larger than the balance is refused.',
        ],
    ],

    'wcat' => [
        'intro' => [
            'title' => 'Catalogue',
            'body' => 'Parts and where to buy them, with prices from the vendors. Use it to fill your robot\'s parts list.',
        ],
        'suggest' => [
            'title' => 'Suggest something missing',
            'body' => 'Can\'t find a part or a vendor? Suggest it and the organisers review it. Only people allowed to suggest see these buttons.',
        ],
        'filters' => [
            'title' => 'Search and filter',
            'body' => 'Search by name or part number, pick a category, or show only parts in stock.',
        ],
        'parts' => [
            'title' => 'The parts',
            'body' => 'Each part shows its offers from different vendors. You can also suggest a price for a part you found cheaper elsewhere.',
        ],
        'suggestions' => [
            'title' => 'Your suggestions',
            'body' => 'What you suggested and its status: waiting for review, approved or rejected.',
        ],
    ],

    'profile' => [
        'intro' => [
            'title' => 'Your profile',
            'body' => 'Your name and email address. The tabs beside or above list the other account settings.',
        ],
        'form' => [
            'title' => 'Name and email',
            'body' => 'Your name in English and Arabic, and the email you sign in with. If you change the email you must verify it again.',
        ],
        'save' => [
            'title' => 'Save',
            'body' => 'Applies your changes.',
        ],
        'delete' => [
            'title' => 'Delete account',
            'body' => 'Permanently removes your account after you confirm with your password. This cannot be undone.',
        ],
    ],

    'security' => [
        'intro' => [
            'title' => 'Security',
            'body' => 'Keep your account safe: a strong password, a second step at sign-in and passkeys.',
        ],
        'password' => [
            'title' => 'Change your password',
            'body' => 'Enter your current password, then a new long, random one twice.',
        ],
        'twofactor' => [
            'title' => 'Two-factor authentication',
            'body' => 'Adds a code from an authenticator app when you sign in. Shown only where it is available.',
        ],
        'passkeys' => [
            'title' => 'Passkeys',
            'body' => 'Sign in with your fingerprint, face or device lock instead of a password. Shown only where it is available.',
        ],
    ],

    'appearance' => [
        'intro' => [
            'title' => 'Appearance',
            'body' => 'Choose how the platform looks on this device.',
        ],
        'tabs' => [
            'title' => 'Light, dark or system',
            'body' => 'Light and dark are fixed; System follows your device. The choice is remembered in this browser.',
        ],
    ],

    'earnings' => [
        'intro' => [
            'title' => 'My earnings',
            'body' => 'Payments the foundation has approved or made to you. Only you can see this page.',
        ],
        'totals' => [
            'title' => 'Totals by year',
            'body' => 'What was paid to you in each year, per currency.',
        ],
        'list' => [
            'title' => 'Your payments',
            'body' => 'Each payment with its amount and status, so you can tell when it is approved and when it is paid.',
        ],
    ],
];
