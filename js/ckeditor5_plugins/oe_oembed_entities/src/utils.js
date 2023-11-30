/**
 * Open a dialog for a Drupal-based plugin.
 *
 * Copy of Drupal.ckeditor5.openDialog, with addition of existingValues parameter.
 *
 * @param {string} url
 *   The URL that contains the contents of the dialog.
 * @param {object} existingValues
 *   Existing values that will be sent via POST to the url for the dialog
 *  contents.
 * @param {function} saveCallback
 *   A function to be called upon saving the dialog.
 * @param {object} dialogSettings
 *   An object containing settings to be passed to the jQuery UI.
 */
export function openDialog(url, existingValues, saveCallback, dialogSettings) {
  // Add a consistent dialog class.
  const classes = dialogSettings.dialogClass
    ? dialogSettings.dialogClass.split(' ')
    : [];
  classes.push('ui-dialog--narrow');
  dialogSettings.dialogClass = classes.join(' ');
  dialogSettings.autoResize =
    window.matchMedia('(min-width: 600px)').matches;
  dialogSettings.width = 'auto';

  const ckeditorAjaxDialog = Drupal.ajax({
    dialog: dialogSettings,
    dialogType: 'modal',
    selector: '.ckeditor5-dialog-loading-link',
    url,
    progress: { type: 'fullscreen' },
    submit: {
      editor_object: existingValues,
    },
  });
  ckeditorAjaxDialog.execute();

  // Store the save callback to be executed when this dialog is closed.
  Drupal.ckeditor5.saveCallback = saveCallback;
}
