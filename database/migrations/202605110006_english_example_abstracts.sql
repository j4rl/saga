UPDATE `{{prefix}}projects` p
INNER JOIN `{{prefix}}users` u ON u.id = p.user_id
SET p.abstract_text = CASE u.username
    WHEN 'elev' THEN 'The study shows that clearer visualization of consumption and changed routines can reduce unnecessary electricity use.'
    WHEN 'elev1' THEN 'The results show that particle levels vary clearly between sampling sites and that surfaces close to traffic produce higher values. The project discusses simple measures such as filters, better cleaning and more deliberate stormwater management.'
    WHEN 'elev2' THEN 'The prototype shows that clear views for week, subject and deadline make planning easier to understand. Test users felt that reminders and fewer choices per screen made the tool easier to use.'
    WHEN 'elev3' THEN 'The conclusion is that crisps feel more fun than mathematics, but the reasoning lacks delimitation, source criticism and analysis. The project is therefore submitted as an example of unserious material that needs feedback before publication.'
    WHEN 'elev4' THEN 'The study shows that the railway route and the establishment of smaller workshops changed both the labour market and local settlement patterns. The project connects local changes to broader Swedish industrialization processes.'
    WHEN 'elev5' THEN 'The results show that many young adults underestimate recurring small expenses and lack a margin for unexpected costs. The project proposes a simple budget model that can be used in personal finance education.'
    WHEN 'elev6' THEN 'The tests show that 3D printing can work for some low-load parts, but that material choice and print orientation are decisive. The project also highlights documentation and safety limits as important before the parts are used.'
    WHEN 'elev7' THEN 'The results show that storage temperature and exposure to air clearly affect the vitamin content. Freshly pressed juice had the highest initial value but lost vitamin C faster than pasteurized juice at room temperature.'
    WHEN 'elev8' THEN 'The study shows that many students quickly check sender and date, but less often follow links to the original source. The project proposes clearer teaching about reverse image search, primary sources and the influence of algorithms.'
    WHEN 'elev9' THEN 'The analysis shows that spoken-language markers, English expressions and direct address are used to create pace and a sense of community. The project discusses how the format influences norms for public language.'
    ELSE p.abstract_text
END
WHERE u.username IN ('elev', 'elev1', 'elev2', 'elev3', 'elev4', 'elev5', 'elev6', 'elev7', 'elev8', 'elev9');
