using System.Collections.ObjectModel;
using System.Windows;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using RPGEditor.Views;

namespace RPGEditor.ViewModels;

public partial class NamedListViewModel : ObservableObject
{
    public string CategoryName { get; }
    public ObservableCollection<string> Items { get; }

    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(SelectedText))]
    private int selectedIndex = -1;

    public string SelectedText
    {
        get => SelectedIndex >= 0 && SelectedIndex < Items.Count ? Items[SelectedIndex] : string.Empty;
        set
        {
            if (SelectedIndex < 0 || SelectedIndex >= Items.Count || Items[SelectedIndex] == value)
                return;

            Items[SelectedIndex] = value;
            OnPropertyChanged();
        }
    }

    public NamedListViewModel(string categoryName, ObservableCollection<string> items)
    {
        CategoryName = categoryName;
        Items = items;
    }

    [RelayCommand]
    private void ChangeMax(Window owner)
    {
        var dialog = new ChangeMaxWindow(CategoryName, Items.Count) { Owner = owner };
        if (dialog.ShowDialog() != true)
            return;

        while (Items.Count < dialog.NewCount)
            Items.Add(string.Empty);
        while (Items.Count > dialog.NewCount)
            Items.RemoveAt(Items.Count - 1);
    }
}
