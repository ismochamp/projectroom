# ProjectRoom — Private Client Delivery & Approval Portal

**Category:** Web Development  
**Role:** Design, theme development, plugin development, database workflow, integration testing and documentation  
**Project type:** Independent working project, September 2026  
**Technology:** WordPress 7.1.2, PHP 8.3, MariaDB/InnoDB, HTML, CSS, Docker

## The operational problem

When project updates, delivery notes and approvals live in separate messages, clients lose context and teams struggle to establish the next agreed step.

## What I built

A private WordPress workspace puts milestones, protected delivery briefs, client approvals, change requests and actor-attributed activity in one place, with access checked against the assigned project member.

- Native WordPress login and client membership checks
- Private delivery brief downloads behind access checks
- Change request, revision and approval state workflow
- Staff project creation and actor-attributed history

## Workflow

Staff creates milestone → Delivers brief → Client requests changes → Staff revises → Client approves

## What was verified

24 live WordPress integration checks passed across administrator and two client accounts, covering cross-project read/download/write denial, CSRF protection and the complete review/revision/approval workflow. Tests exercised the actual WordPress routes and database-backed actions, rather than a static design or a separate preview implementation. Temporary test records were removed. The provided screenshots show the running local WordPress application.

## Design decisions

The client needs clear next steps and confidence that unrelated project information is private. Native WordPress authentication supplies established account handling; the plugin checks membership again on every project operation. Private download handlers avoid publishing delivery content as public media. A controlled state workflow keeps approvals and revisions attributable to the right actor.

## Evidence and practical scope

- Source theme and plugin are editable and packaged for installation.
- The local Docker setup starts a real WordPress instance with persistent storage.
- `TEST_RESULTS.md` records the live checks; `test_wordpress.py` can reproduce them.
- `screenshots/` contains actual application captures; no videos are included.

Locally verified independent project with fictional sample clients and project content. One assigned client per project; administrator staff. No file upload, email, invoicing or public hosting. Delivery briefs are private text downloads.

No measured client business improvement is claimed. The evidence is the implemented workflow and the recorded behavior under the checks above.
