from pathlib import Path
p = Path('edit_quiz_page.html')
text = p.read_text(encoding='utf-8', errors='ignore')
for i, line in enumerate(text.splitlines(), 1):
    if any(token in line for token in ['name="action"', 'name="quiz_id"', 'name="group_ids[]"', 'name="existing_image[]"', 'name="questions[]"', 'name="choice_1[]"', 'name="choice_2[]"', 'name="choice_3[]"', 'name="choice_4[]"', 'name="correct_answer[]"']):
        print(i, line)
    if i > 320:
        break
