// Add util functions to component namespace;
var core_admin = {};

/**
 * Set a admin setting text setting and save the page.
 *
 * @method set_setting_text
 * @param {String} tabname The admin settings tab the settings page is contained in.
 * @param {String} settingspagename The name of the settings page containing the setting.
 * @param {String} settingname The name of the setting to set.
 * @param {String} settingvalue The new value of the setting
 * @return {Boolean}
 */
core_admin.set_setting_text = async function(tabname, settingspagename, settingname, settingvalue) {
    var element;

    // Login as admin user.
    explainTest('Login as admin');
    await core_user.login('admin');

    // Add some items to the custom menu setting.
    explainTest('Go to "Site administration" page');
    element = await reader.findInPage('link', /Site administration/);

    // Follow the link.
    await reader.doDefault(element);

    await reader.waitForInteraction(true);

    explainTest('Show the "' + tabname + '" tab');
    // Switch to the correct tab.
    element = await reader.findInPage('tab', new RegExp(tabname));
    await reader.doDefault(element);

    explainTest('Go to the "' + settingspagename + '" page');
    // Proceed to the theme settings page.
    element = await reader.findInPage('link', new RegExp(settingspagename));
    await reader.doDefault(element);

    await reader.waitForInteraction(true);

    explainTest('Set the value of setting "' + settingname + '"');
    element = await reader.findInPage('textField', new RegExp(settingname));

    await reader.focus(element);
    await reader.enterText(element, settingvalue);
    explainTest('Save the changes');
    element = await reader.findInPage('button', /Save changes/);
    await reader.doDefault(element);

    await reader.waitForInteraction(true);
    return true;
};
