# Essential Features:

As a Poll Creator, 
 * I should be able to register - and - login with email, password or social login
 * I should be able to create polls (question and multiple options) - It should be a very simple process. 
 * I should be able to share the polls via Social Media, WhatsApp
 * Polls will have specific time period to be active - 3 days, 5 days, 1 week.
 * There should be a simple data analysis feature for every poll results.
 * There should be a overall account level analysis - number of polls created, engagement level etc.

As a General User,
 * Anyone can participate in Polls without registration or login (it is for the public - anonymous participation)

As a System Admin,
 * I should be able to use a secured login only - NO Social Login
 * I should be able to manage registered accounts - if required suspend the account or poll - and resume when needed.
 * I should be able to see overall platform performance (members, number of polls, engagement etc.)
 * I should be able to see the list of all active, inactive polls.

# Tech Decisions

* Social login should happen through firebase
* UI should be mobile browser friendly
* It should work on all modern browsers (Chrome, Firefox, Safari, Edge)
* No third party plugins should be used.

# CI/CD

* I want to use Git Actions for FTP Deployment to a remote server. I will provide the FTP details.

