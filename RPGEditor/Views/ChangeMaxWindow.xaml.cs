using System.Windows;

namespace RPGEditor.Views;

public partial class ChangeMaxWindow : Window
{
    public int NewCount { get; private set; }

    public ChangeMaxWindow(string categoryName, int currentCount)
    {
        InitializeComponent();
        CategoryLabel.Text = $"'{categoryName}'의 최대 개수를 입력하세요.";
        CountTextBox.Text = currentCount.ToString();
    }

    private void OkButton_Click(object sender, RoutedEventArgs e)
    {
        if (!int.TryParse(CountTextBox.Text, out var count) || count < 1)
        {
            MessageBox.Show(this, "1 이상의 숫자를 입력하세요.", "오류", MessageBoxButton.OK, MessageBoxImage.Warning);
            return;
        }

        NewCount = count;
        DialogResult = true;
    }
}
