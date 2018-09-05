// Add util functions to component namespace;
var core_user = {};

/**
 * Perform a login to Moodle as the specified user. Will logout first if required.
 *
 * @method login
 * @param {String} username The user to login as.
 * @param {String} password The password to login as. Optional - default admin user is known.
 * @return {Boolean}
 */
core_user.login = async function(username, password) {
    if (username == 'admin') {
        password = 'admin';
    }

    var element;
    var form;
    var usernameField;
    var passwordField;
    var loginButton;
    var pageTitle;

    // Try and logout...
    element = await reader.findInPage('link', 'Log out');
    if (element) {
        await reader.doDefault(element);
    }
    pageTitle = await reader.getPageTitle();
    expect(pageTitle).to.be('Acceptance test site');

    // Go to login page.
    element = await reader.findInPage('link', 'Log in');

    // Proceed to the login page.
    await reader.doDefault(element);

    pageTitle = await reader.getPageTitle();
    expect(pageTitle).to.be('Acceptance test site: Log in to the site');

    // Use login form to login as admin.
    form = await reader.findInPage('form');

    // Find the username field.
    usernameField = await reader.find(form, 'textField', /Username/);
    expect(usernameField).not.to.be(null);

    // Enter the username.
    await reader.enterText(usernameField, "admin");

    // Find the password field.
    passwordField = await reader.find(form, 'textField', /Password/);

    // Enter the password.
    await reader.enterText(passwordField, password);

    // Find the login button.
    loginButton = await reader.find(form, 'button', 'Log in');

    // Complete the login.
    await reader.doDefault(loginButton);
    // Delay for form submission.
    await reader.waitForInteraction(true);
    pageTitle = await reader.getPageTitle();
    expect(pageTitle).to.be('Dashboard');

    return true;
};
