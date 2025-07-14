# RM-Link-to-Headings
Displays a list of heading links that scroll to that specific header on the page. ACF repeater field with machine name (link_to_headings) and acf subfield as plain text with machine name (blog_page_headings). The heading links are shown with a shortcode.

ACF Field Setup Instructions:
For this plugin to work, you need to set up your ACF fields correctly.

Install & Activate ACF: Make sure the Advanced Custom Fields plugin (free or Pro) is installed and activated on your WordPress site.

Create a Field Group:

Go to ACF > Field Groups in your WordPress admin.

Click Add New.

Give your Field Group a descriptive name (e.g., "Post Headings Links").

Under Location Rules, set it to Post Type is equal to Post.

Add a Repeater Field:

Click + Add Field.

Field Label: Link to Headings

Field Name: link_to_headings (This must be exactly link_to_headings for the plugin to find it).

Field Type: Select Repeater.

Add a Sub Field (within the Repeater):

Inside the link_to_headings repeater field, click + Add Field again.

Field Label: Heading Text (or Link to Heading Text for clarity)

Field Name: link_to_headings (This must also be exactly link_to_headings as per your request).

Field Type: Select Text.

Save Changes: Click Save Changes for your Field Group.

