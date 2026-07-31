using System.Globalization;
using System.Windows.Data;

namespace RPGEditor.Converters;

public class IndexToDisplayConverter : IValueConverter
{
    public object Convert(object? value, Type targetType, object parameter, CultureInfo culture)
        => value is int index ? (index + 1).ToString("D2") : string.Empty;

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture)
        => throw new NotSupportedException();
}
