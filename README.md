# Task activity (mod_task)

A Moodle activity module that lets a teacher set a task for students to
respond to. Students must post their own response before they can see the
teacher's response (optionally flagged as a model answer) and the responses
of their peers. Students may respond anonymously, react to responses with
emoji, and reply to one another. A Task can also be embedded inline in a
Label or Book chapter via the companion [filter_task](../../filter/task)
filter.

## Features

- **Answer-before-you-see gating** — a student's own response, the teacher
  response and other students' responses stay hidden until the student posts
  their own response. All of the gating is enforced server-side, so an
  embedded Task is exactly as safe as the activity page.
- **Teacher response**, optionally flagged as a model answer once revealed.
- **Anonymous responses** — students can post under a hidden name; staff
  always see the real author.
- **Threaded replies** and **emoji reactions** on any post, configured
  site-wide.
- **Notification preferences** per Task (all responses and replies, responses
  only, replies to my response only, or muted).
- A "*x* new responses" badge on the course page activity card.
- Configurable **Task types**, each mapping to a set of CSS classes used to
  style the description panel (e.g. Explore, Watch, Read, Write).
- Embeddable in any filtered content using `{task:Task name}`.

## Requirements

- Moodle 4.5 (build 2024100700) or later. Supported on Moodle 4.5 and 5.1.

## Installation

1. Copy the plugin into the `mod/task` directory of your Moodle site (or
   install the ZIP via *Site administration → Plugins → Install plugins*).
2. Log in as an administrator and complete the upgrade when prompted.
3. Add a Task activity to a course from the activity chooser.

## Usage

1. Add a Task activity and enter the **Task description** (shown to everyone,
   including before a student responds) and, optionally, a **Teacher
   response** and its **Task type**.
2. Students open the activity and post their response, choosing whether to
   respond **anonymously**.
3. Once a student has responded, they can see the teacher response, every
   other student's responses, and can reply to and react to any post.
4. To embed the same Task elsewhere in the course (a Label or Book chapter,
   for example), install [filter_task](../../filter/task) and add
   `{task:Task name}` to the content.

## Settings

Settings live at *Site administration → Plugins → Activity modules → Task*:

- **Reaction emoji** — the `shortcode:emoji` pairs offered as reactions.
- **Task types** — one `shortname|name|CSS classes` definition per line,
  controlling the options in each activity's "Task type" dropdown.
- **Show new-response badge** — toggles the "*x* new responses" badge on the
  course page activity card.

## Capabilities

- `mod/task:addinstance` — add a new Task to a course.
- `mod/task:view` — view the Task activity.
- `mod/task:respond` — post a response or reply, and react to posts.
- `mod/task:viewallresponses` — see every response (and the teacher response)
  without posting first, and see real names behind anonymous responses.
- `mod/task:manageresponses` — edit or delete any response or reply.

## Privacy

The plugin stores users' posts (including whether posted anonymously),
reactions, notification preferences and last-viewed times, and implements the
Moodle Privacy API for export and deletion.

## Third-party libraries

- [Quill](https://github.com/slab/quill) 2.0.3 (BSD 3-Clause) — rich text editor.

## License

Copyright 2026 Andrew Rowatt <A.J.Rowatt@massey.ac.nz>

Licensed under the GNU GPL v3 or later. See [LICENSE](LICENSE).
