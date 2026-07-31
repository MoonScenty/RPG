using System.ComponentModel;

namespace RPGEditor.Models;

public interface IDatabaseEntry : INotifyPropertyChanged
{
    int Id { get; set; }
    string Name { get; set; }
    string Note { get; set; }
}
