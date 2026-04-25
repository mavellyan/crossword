const MOCK_WORDS = [
  { id: 1, solution: 'ALMA', definition: 'Piros vagy zold gyumolcs.' },
  { id: 2, solution: 'KÖRTE', definition: 'Osszel erik, almahoz hasonlo gyumolcs.' },
  { id: 3, solution: 'BARÁT', definition: 'Kozeli ismeros, akiben megbizol.' },
  { id: 4, solution: 'VÍZILÓ', definition: 'Nagytestu afrikai emlos.' },
  { id: 5, solution: 'ÁRNYÉK', definition: 'Fenytakaras utan keletkezo sotet forma.' },
  { id: 6, solution: 'ERDŐ', definition: 'Fakkal surun benott terulet.' },
  { id: 7, solution: 'NYÁR', definition: 'Az ev legmelegebb evszaka.' },
  { id: 8, solution: 'ŐSZ', definition: 'Lombhullas idoszaka.' },
  { id: 9, solution: 'TALÁNY', definition: 'Nehezen megfejtheto kerdes vagy rejtely.' },
  { id: 10, solution: 'TÜKÖR', definition: 'Sima felulet, amely visszaveri a kepet.' },
  { id: 11, solution: 'UTAZÁS', definition: 'Helyvaltoztatas hosszabb tavon.' },
  { id: 12, solution: 'ÖRÖM', definition: 'Pozitiv erzelmi allapot.' },
  { id: 13, solution: 'HÁZ', definition: 'Lakoepulet.' },
  { id: 14, solution: 'EGÉR', definition: 'Kis testu ragcsalo.' },
  { id: 15, solution: 'SZÖVEG', definition: 'Leirt mondatok osszessege.' },
  { id: 16, solution: 'BÁTOR', definition: 'Nem fel a nehez helyzetektol.' },
  { id: 17, solution: 'TŰZ', definition: 'Egessel jaro jelenseg.' },
  { id: 18, solution: 'AJTÓ', definition: 'Bejaratot zaro nyilo.' },
  { id: 19, solution: 'VILLÁM', definition: 'Zivatarban lathato fenyjelenseg.' },
  { id: 20, solution: 'IDŐ', definition: 'Mulas, tartam vagy idopont fogalma.' },
  { id: 21, solution: 'CSERESZNYE', definition: 'A nyar elejen ero, apro piros gyumolcs.' },
  { id: 22, solution: 'SZOFTVER', definition: 'Szamitogepen futtathato programrendszer.' },
  { id: 23, solution: 'KEZDÉS', definition: 'Valaminek az elinditasa.' },
  { id: 24, solution: 'ÉRTÉK', definition: 'Valaminek a fontossaga vagy ara.' },
  { id: 25, solution: 'PÉLDA', definition: 'Valami szemleltetesere szolgalo minta.' },
  { id: 26, solution: 'HÍR', definition: 'Friss informacio egy esemenyrol.' },
  { id: 27, solution: 'SÉTA', definition: 'Lassu tempoju gyaloglas.' },
  { id: 28, solution: 'GÉP', definition: 'Mechanikus vagy elektronikus eszkoz.' },
  { id: 29, solution: 'ORSÓ', definition: 'Fonallal hasznalt kis hengeres eszkoz.' },
  { id: 30, solution: 'ÉLET', definition: 'A letezes biologiai folyamata.' },
]

/**
 * Returns all available mock words.
 *
 * @returns {Array<{ id: number, solution: string, definition: string }>}
 */
export function getAllMockWords() {
  return MOCK_WORDS
}

/**
 * Returns words that contain the requested letter.
 *
 * @param {string} letter One uppercase letter.
 * @returns {Array<{ id: number, solution: string, definition: string }>}
 */
export function getWordsForLetter(letter) {
  if (!letter) {
    return []
  }

  return MOCK_WORDS.filter((word) => word.solution.includes(letter))
}
