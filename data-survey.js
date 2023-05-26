var data_survey_chosen_row = jQuery(`[name="item_meta[3890]"]`).val()
if ('' != data_survey_chosen_row) {
    var data_survey_chosen_cols = data_survey_chosen_row.split(`,`)
    var data_survey_chosen_field = data_survey_chosen_cols[0]
    var data_survey_chosen_display_type = data_survey_chosen_cols[data_survey_chosen_cols.length - 2].trim()
    jQuery(`[name="item_meta[3890]"]`).val(data_survey_chosen_field)
    jQuery(`[name="item_meta[3891]"]`).val(data_survey_chosen_display_type)
    var data_survey_chosen_field_container = jQuery(`#frm_field_${data_survey_chosen_field}_container`)
    data_survey_chosen_field_container.siblings().hide()
    data_survey_chosen_field_container.find(`[type="checkbox"], [type="radio"]`).click(function () {
        data_survey_chosen_field_container.siblings().find(`[type="submit"]`).click()
    })
}