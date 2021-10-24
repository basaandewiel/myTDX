**myTDX** (my tiny todo extended)

As the name suggests, this project is heavily based on an old but very well done ajax todolist
written by maxpozdeev/mytinytodo (http://www.mytinytodo.net/). It also works quite well on mobile phones.

If you are in a hurry, you may just rename the "db_sample/" folder in place of "db/", so it
bypasses the initial required call of "setup.php". This way you will see it in action quickly.
Otherwise you will be asked about what and how to configure the todo list.

My fork adds some important features, especially for *sharing with others*, including developers.
I wanted a small, fast, no-nonsense bugtracking system which anyone can understand, including my
clients and the end users.

- most notably, tasks now have visible identifiers to refer to. There was no way to point to tasks
unambiguously.

- in addition to existing URL, notes can also embed cross-references to other tasks (just use #taskid).
When you click on them you are redirected to the respective task.

- this cross-reference is done via an enhanced search. You can now look for "#123" to go to
the respective task (and its notes will be opened). The prefixed value in the search string
forces a search by id only. Looking for "123" will match titles, notes or ids of the tasks, which
is still convenient to find all the tasks that refer to 123 alike.

- the incoming index URL also can provide the search string (use "?i=taskid" or "?s=keyword"). Thus,
pointing directly to a specific task is done with ?i=123. This is convenient in order to send links
by email, e.g. When you want all related tasks and cross-references you may prefer "?s=123"

- style of tags can be customized, in addition to a few special notations: =state, @user, !highlight.
I intend to use them like "=acknoledged" or "=closed" for bug tracking, to use "!discussion", and
to assign someone to a task with @buddy for example.

- the tag list at the end of a task being edited now also shows tags from other lists. There is a setting
to change this but it helps keep things tidy. The grayed tags are "borrowed" from the other lists.

- I added a backup system in case you are using sqlite (which I always receommend unless you really
have a reason to use mysql). Backuped files are kept according to the main settings, and restoration
must be done manually if ever something terrible happens

