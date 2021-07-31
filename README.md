# quasiForm
MODX Revolution Extra for processing forms.

# How to use

## 1. Create resource with JSON type
## 2. Add this code to content:
```
[[!quasiForm?
    &fields=`[
    {
      "name": "name",
      "label": "Имя",
      "validators": {
        "required": true,
        "minLength": 2,
        "maxLength": 255
      },
      "modifiers": {
        "strip_tags": true,
        "trim": true
      }
    },
    {
      "name": "text",
      "label": "Текст",
      "validators": {
        "required": true
      },
      "modifiers": {
        "strip_tags": true,
        "trim": true
      }
    }
  ]`
	&plugins=`[
		{
			"name": "quasiFormEmail",
			"options": {
				"emailTpl": "FeedbackEmail",
				"recipientEmails": "noreply@foo.bar",
				"senderEmail": "[[++emailsender]]",
				"senderName": "Guest",
				"subject": "Feedback"
			}
		}
	]`
]]
```
## 3. Create JavaScript-form which will send form to created resource.

# Plugins
* quasiEmailForm - to send emails.
* quasiFormSave - to save sent forms in database.
* Another plugins are not ready, but exists.

# Roadmap
* Send email to user
* Page for validation forms
* Visual editor of forms
