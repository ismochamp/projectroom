# ProjectRoom verification

Verified 2026-09-28T07:28:11+00:00 against real local WordPress 7.1.2 / PHP 8.3 / MariaDB.

- PASS: Anonymous homepage contains only sign-in content
- PASS: Assigned client sees own project without other project data
- PASS: Direct URL to another client project rejected
- PASS: Cross-project private delivery download rejected
- PASS: Anonymous private delivery download rejected
- PASS: Assigned client can download its own private brief
- PASS: Client cannot create projects
- PASS: Staff creates persistent project assigned to a WordPress client
- PASS: Staff creates persistent milestone
- PASS: Invalid state-change nonce rejected
- PASS: Client cannot reuse a staff delivery form
- PASS: Staff delivery moves an active milestone to review
- PASS: Other client cannot mutate a foreign project
- PASS: Milestone identifier cannot escape the selected project
- PASS: Valid client nonce does not grant staff delivery capability
- PASS: Change request requires actionable feedback
- PASS: Client change request persists with actor and activity history
- PASS: Stale approval rejected when milestone is not awaiting review
- PASS: Staff can revise and redeliver after requested changes
- PASS: Assigned client approval updates progress and durable activity
- PASS: Repeated approval rejected after completion
- PASS: Client cannot delete project or history
- PASS: Staff project deletion requires a valid nonce
- PASS: Staff removes fixture project, milestones and activity

All test-created records removed. Uses native WordPress users, cookies, capabilities and CSRF nonces. Private delivery briefs are served only after project membership checks. Public hosting and external notifications are outside this build.
