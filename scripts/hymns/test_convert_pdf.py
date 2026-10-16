import unittest

from convert_pdf import find_boundaries, normalized_title, split_lyrics


def source(*texts):
    return [{"page": 4, "line": i + 1, "text": text} for i, text in enumerate(texts)]


class ConversionTest(unittest.TestCase):
    def split(self, *texts):
        return split_lyrics(source(*texts), 1, "sample", [])

    def test_tune_is_not_the_title(self):
        hymn, _, _ = self.split("Wells - 7 7.7.7.7.7.", "Father, Son, and Holy Ghost,", "One in Three, and Three in One,")
        self.assertEqual(hymn["title"], "Father, Son, and Holy Ghost")
        self.assertEqual(hymn["tune"], "Wells")

    def test_named_tune_and_unseparated_metre_are_not_titles(self):
        hymn, _, _ = self.split("EIN’FESTE BURG - 878766667", "1. A mighty fortress is our God,", "A bulwark never failing;")
        self.assertEqual(hymn["title"], "A mighty fortress is our God")
        hymn, _, _ = self.split("ODE TO JOY", "1. Joyful, joyful, we adore Thee,", "God of glory, Lord of love;")
        self.assertEqual(hymn["title"], "Joyful, joyful, we adore Thee")

    def test_zero_and_one_in_a_chorus_remain_source_lyrics(self):
        hymn, _, _ = self.split("1. Living for Jesus", "CHORUS", "0 Jesus, Lord and Saviour,", "1 give myself to Thee;", "2. Living for Jesus who died")
        self.assertEqual([x["kind"] for x in hymn["stanzas"]], ["verse", "chorus", "verse"])
        self.assertIn("0 Jesus", hymn["stanzas"][1]["text"])
        self.assertIn("1 give myself", hymn["stanzas"][1]["text"])

    def test_redundant_standalone_marker_is_not_an_empty_stanza(self):
        hymn, _, provenance = self.split("1. First verse", "2", "2. Second verse")
        self.assertEqual([x["text"] for x in hymn["stanzas"]], ["First verse", "Second verse"])
        self.assertEqual(len(provenance), 3)

    def test_numbered_chorus_retains_its_source_label(self):
        hymn, _, _ = self.split("1. First verse", "CHORUS", "2. Sing to the Lord", "3. Another verse")
        self.assertEqual(hymn["stanzas"][1]["kind"], "chorus")
        self.assertEqual(hymn["stanzas"][1]["label"], "2")
        self.assertEqual(hymn["stanzas"][1]["text"], "Sing to the Lord")

    def test_incomplete_book_cannot_silently_pass(self):
        with self.assertRaises(ValueError):
            find_boundaries(source("1", "First", "3", "Third"), expected_count=3)

    def test_normalization_preserves_leading_words(self):
        self.assertEqual(normalized_title(' “The Lord is King'), "the lord is king")


if __name__ == "__main__":
    unittest.main()
