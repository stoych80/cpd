# CPD plugin

Continued Professional Development activities recording based on cycles. For the users to record their CPD activities - a frontend page is created via shortcode which uses a plugin template by default that can be overloaded for the client. Once installed and activated the CPD must be configured in the admin area for the following settings:
CPD Email chase up notifications 
Low Points notification period 
Number of Points needed to complete CPD cycle.
CPD cycles start on 
Required for Cycles to start
CPD cycle duration 
Required if the above is set
Required for Cycles to start
Period between CPD cycles 
CPD cycles grace period 
"Request extension" ability period  - 3 emails are sent - "User requested an extension", "Admin approved requested extension", "Admin rejected requested extension" - configurable with "Notification Templates" (see below).
Audit - "Request a review" ability period  - 2 emails are sent - "User requested a review - notify admin", "User requested a review - notify user" - configurable with "Notification Templates" (see below).
What fields must be filled in a CPD entry in order its Points to count against the current cycle completion.  - here you select from a list of already created fields in the "CPD Entry Fields" admin section described below.

CPD Entry Fields : here you specify the fields, their type, whether they are required, etc. that users will use when they create CPD entries. For the "Activity Type" dropdown field options - there are a number of rules that can be specified to determine how many points/hours a particular entry can give based on its type: 
Audit Report = A WP listgrid with all users who have CPD entries with points/hours, cycle(s) information. A search filters included.
Request Extension ability - a list of all users who requested an extension for their current cycle. Admin can approve.reject, amend the requested period. A search filters included.

Automatic database compatibility management based on the plugin version - if the plugin version is incremented - on update of the plugin in the WordPress admin area - it checks for a corresponding database upgrade script(s) to be run.
