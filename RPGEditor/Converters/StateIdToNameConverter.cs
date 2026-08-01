using System.Globalization;
using System.Linq;
using System.Windows.Data;
using RPGEditor.Models;

namespace RPGEditor.Converters;

/// <summary>상태 ID와 States.json 목록을 받아 "ID: 이름" 형태로 표시한다.</summary>
public class StateIdToNameConverter : IMultiValueConverter
{
    public object Convert(object[] values, Type targetType, object parameter, CultureInfo culture)
    {
        // DataGrid의 "새 행 추가" 자리표시자 등 실제 값이 없는 경우 조용히 빈 문자열 반환
        if (values.Length < 2 || values[0] is not int stateId || values[1] is not IEnumerable<GameState> states)
            return string.Empty;

        var state = states.FirstOrDefault(s => s.Id == stateId);
        return state is not null ? $"{state.Id}: {state.Name}" : stateId.ToString();
    }

    public object[] ConvertBack(object value, Type[] targetTypes, object parameter, CultureInfo culture)
        => throw new NotSupportedException();
}
