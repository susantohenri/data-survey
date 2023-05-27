var data_survey_chosen_row = jQuery(`[name="item_meta[3890]"]`).val()
if ('' != data_survey_chosen_row) {
    var data_survey_chosen_cols = data_survey_chosen_row.split(`,`)
    var data_survey_chosen_field = data_survey_chosen_cols[0]
    var data_survey_chosen_display_type = data_survey_chosen_cols[data_survey_chosen_cols.length - 2].trim()
    var data_survey_chosen_field_container = jQuery(`#frm_field_${data_survey_chosen_field}_container`)
    var data_survey_question_group = jQuery(`#frm_field_3834_container`)

    jQuery(`[name="item_meta[3890]"]`).val(data_survey_chosen_field)
    jQuery(`[name="item_meta[3891]"]`).val(data_survey_chosen_display_type)

    if (data_survey_question_group.find(data_survey_chosen_field_container).length > 0) data_survey_question_group.siblings().hide()
    data_survey_chosen_field_container.siblings().hide()

    data_survey_chosen_field_container.find(`[type="checkbox"], [type="radio"]`).click(function () {
        data_survey_question_group.siblings().find(`[type="submit"]`).click()
    })
}