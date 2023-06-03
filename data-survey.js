var data_survey_chosen_row = jQuery(`[name="item_meta[3890]"]`).val()
var data_survey_form = jQuery(`[id="form_5datasurvey2"]`)

if ('' != data_survey_chosen_row) {
    var data_survey_chosen_cols = data_survey_chosen_row.split(`,`)
    var data_survey_chosen_field = data_survey_chosen_cols[0]
    var data_survey_chosen_display_type = data_survey_chosen_cols[data_survey_chosen_cols.length - 2].trim()

    jQuery(`[name="item_meta[3890]"]`).val(data_survey_chosen_field)
    jQuery(`[name="item_meta[3891]"]`).val(data_survey_chosen_display_type)

    data_survey_form.find(`[type="checkbox"], [type="radio"]`).click(function () {
        data_survey_form.submit()
    })
}